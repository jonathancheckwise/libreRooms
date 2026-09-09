<?php

namespace App\Validation;

use Illuminate\Validation\Rule;
use App\Models\Room;
use App\Models\Reservation;
use App\Enums\PriceModes;

class ReservationRules
{
    public static function prepare(): void
    {

    }

    protected static function rules(Room $room, String $contact_type): array
    {
        $rules = [
            'res_title'             => ['required','string','max:100'],
            'res_description'       => ['nullable','string','max:1000'],
            'events'                => ['required', 'array', 'min:1'],

            // Dates
            'events.*.start'        => ['required', 'date'],
            'events.*.end'          => ['required', 'date'],

            'events.*.options'      => ['nullable', 'array'],
            'events.*.options.*'    => [
                                        'integer',
                                        Rule::exists('room_options', 'id')
                                            ->where('room_id', $room->id),
                                        ],
            'discounts.*'           => [
                                        'integer',
                                        Rule::exists('room_discounts', 'id')
                                            ->where('room_id', $room->id)
                                            ->where(function($query) use ($contact_type) {
                                                $query->whereNull('limit_to_contact_type')
                                                      ->orWhere('limit_to_contact_type', $contact_type);
                                            }),
                                        ],
            'donation'              => ['nullable', 'numeric', 'min:0',
                                        Rule::requiredIf(fn () => $room->price_mode == PriceModes::FREE),
                                        ],
        ];

        return $rules;
    }

    public static function createRules(Room $room, String $contactType): array
    {
        $rules = self::rules($room, $contactType);
        $rules['events.*.uid'] = ['nullable', 'string', 'size:0'];
        return $rules;
    }

    public static function updateRules(Reservation $reservation, String $contactType): array
    {
        $rules = self::rules($reservation->room, $contactType);
        $rules['events.*.uid'] = ['nullable', Rule::in($reservation?->events->pluck('uid')->all())];
        return $rules;
    }

    public static function adminRules(): array
    {
        $rules = [];
        $rules['special_discount'] = ['nullable', 'numeric', 'min:0'];
        $rules['custom_message'] = ['nullable', 'string', 'max:1000'];
        $rules['is_free'] = ['boolean'];
        $rules['admin_price'] = ['nullable', 'numeric', 'min:0'];
        $rules['action'] = ['required', Rule::in(['prepare', 'confirm'])];
        return $rules;
    }
}
