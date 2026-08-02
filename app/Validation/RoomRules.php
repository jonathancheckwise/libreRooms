<?php

namespace App\Validation;

use App\Enums\CalendarViewModes;
use App\Enums\CharterModes;
use App\Enums\EmbedCalendarModes;
use App\Enums\ExternalSlotProviders;
use App\Enums\PriceModes;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomRules
{
    public static function prepare(Request $request)
    {
        $owner = Owner::find($request->input('owner_id'));
        if ($owner && ! $owner->caldavSettings()->valid()) {
            $request->merge(['external_slot_provider' => null]);
        }
        if (! $request->has('allowed_weekdays')) {
            $request->merge(['allowed_weekdays' => []]);
        }

        // Équipements (La Pépite) : aucune case cochée → tableau vide (permet de
        // tout décocher lors d'une mise à jour).
        if (! $request->has('equipments')) {
            $request->merge(['equipments' => []]);
        }

        // Salle « sur demande » (La Pépite) : pas de prix → on force le prix
        // journée à 0 (colonne non nulle en base) pour ne pas bloquer la
        // validation ni l'enregistrement.
        if ($request->boolean('on_request') && blank($request->input('price_full_day'))) {
            $request->merge(['price_full_day' => 0]);
        }
    }

    public static function rules(Request $request): array
    {
        $user = auth()->user();
        $userId = $user->id;

        // Get owner IDs that the user can use
        if ($user->is_global_admin) {
            $ownerRule = Rule::exists('owners', 'id');
        } else {
            $ownerRule = Rule::exists('owner_user', 'owner_id')
                ->where('user_id', $userId)
                ->where('role', 'admin');
        }

        return [
            'owner_id' => [
                'required',
                'integer',
                $ownerRule,
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => ['nullable', 'string'],

            // Address (all required)
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:25'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],

            // GPS coordinates (required)
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            // Images
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'exists:images,id'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['string', 'regex:/^(existing|new):\d+$/'],
            'active' => ['boolean'],
            'is_public' => ['boolean'],
            // Salle « sur demande » (La Pépite) : pas de prix affiché.
            'on_request' => ['boolean'],
            // Salle non réservable (La Garderie) / réservation facultative (La Chill).
            'bookable' => ['boolean'],
            'booking_optional' => ['boolean'],
            // Fenêtres de disponibilité par jour (ISO 1=lun..7=dim).
            'availability_windows' => ['nullable', 'array'],
            'availability_windows.*.weekday' => ['nullable', 'integer', 'between:1,7'],
            'availability_windows.*.start' => ['nullable', 'date_format:H:i'],
            'availability_windows.*.end' => ['nullable', 'date_format:H:i'],
            // Équipements (La Pépite) : wifi, visio, pmr, sonorisation…
            'equipments' => ['nullable', 'array'],
            'equipments.*' => ['string', 'max:50'],

            // Price configuration
            'price_mode' => [
                'required',
                Rule::in(array_map(fn ($case) => $case->value, PriceModes::cases())),
            ],
            'free_price_explanation' => ['nullable', 'string'],
            'price_short' => ['nullable', 'numeric', 'min:0'],
            // Non requis si la salle est « sur demande » (pas de prix).
            'price_full_day' => ['nullable', 'required_unless:on_request,1', 'numeric', 'min:0'],
            // Prix par salle (La Pépite) : demi-journée + horaire, tarifs
            // lucratif (price_*) et non-lucratif (price_np_*).
            'price_half_day' => ['nullable', 'numeric', 'min:0'],
            'price_hourly' => ['nullable', 'numeric', 'min:0'],
            'price_np_full_day' => ['nullable', 'numeric', 'min:0'],
            'price_np_half_day' => ['nullable', 'numeric', 'min:0'],
            'price_np_hourly' => ['nullable', 'numeric', 'min:0'],
            // Anciens champs (durées) désormais définis globalement : facultatifs
            'max_hours_short' => ['nullable', 'integer', 'min:1'],
            'max_hours_half_day' => ['nullable', 'integer', 'min:1'],
            'always_short_after' => ['nullable', 'integer', 'min:0', 'max:24'],
            'always_short_before' => ['nullable', 'integer', 'min:0', 'max:24'],
            'allow_late_end_hour' => ['nullable', 'integer', 'min:0'],

            // Reservation rules
            'reservation_cutoff_days' => ['nullable', 'integer', 'min:0'],
            'reservation_advance_limit' => ['nullable', 'integer', 'min:0'],

            // Booking rules
            'allowed_weekdays' => ['array'],
            'allowed_weekdays.*' => ['integer', 'between:1,7'],
            'day_start_time' => ['nullable', 'date_format:H:i'],
            'day_end_time' => ['nullable', 'date_format:H:i', 'after:day_start_time'],

            // Discounts and donations
            'use_special_discount' => ['boolean'],
            'use_donation' => ['boolean'],

            // Charter configuration
            'charter_mode' => [
                'required',
                Rule::in(array_map(fn ($case) => $case->value, CharterModes::cases())),
            ],
            // charter_str is required unless charter_mode is NONE
            'charter_str' => [
                $request->input('charter_mode') !== CharterModes::NONE->value ? 'required' : 'nullable',
                'string',
            ],
            'custom_message' => ['nullable', 'string'],

            'secret_message' => ['nullable', 'string'],
            'secret_message_days_before' => ['nullable', 'integer', 'min:1'],

            // Calendar configuration
            'external_slot_provider' => [
                'nullable',
                Rule::in(array_map(fn ($case) => $case->value, ExternalSlotProviders::cases())),
            ],
            'dav_calendar' => [
                $request->input('external_slot_provider') === ExternalSlotProviders::CALDAV->value ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'embed_calendar_mode' => [
                Rule::in(array_map(fn ($case) => $case->value, EmbedCalendarModes::cases())),
            ],
            'calendar_view_mode' => [
                Rule::in(array_map(fn ($case) => $case->value, CalendarViewModes::cases())),
            ],

            // Regional settings
            'timezone' => ['nullable', 'string', 'max:100'],

            'disable_mailer' => ['nullable', 'boolean'],
        ];
    }
}
