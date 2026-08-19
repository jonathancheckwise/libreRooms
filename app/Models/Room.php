<?php

namespace App\Models;

use App\Enums\CalendarViewModes;
use App\Enums\CharterModes;
use App\Enums\EmbedCalendarModes;
use App\Enums\ExternalSlotProviders;
use App\Enums\PriceModes;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'street',
        'postal_code',
        'city',
        'country',
        'latitude',
        'longitude',
        'active',
        'is_public',
        'on_request',
        'bookable',
        'booking_optional',
        'members_only',
        'equipments',
        'price_mode',
        'free_price_explanation',
        'price_short',
        'price_full_day',
        'price_half_day',
        'price_hourly',
        'price_np_full_day',
        'price_np_half_day',
        'price_np_hourly',
        'max_hours_short',
        'max_hours_half_day',
        'always_short_after',
        'always_short_before',
        'allow_late_end_hour',
        'reservation_cutoff_days',
        'reservation_advance_limit',
        'allowed_weekdays',
        'day_start_time',
        'day_end_time',
        'use_special_discount',
        'use_donation',
        'charter_mode',
        'charter_str',
        'custom_message',
        'secret_message',
        'secret_message_days_before',
        'external_slot_provider',
        'dav_calendar',
        'embed_calendar_mode',
        'calendar_view_mode',
        'timezone',
        'disable_mailer',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_public' => 'boolean',
        'on_request' => 'boolean',
        'bookable' => 'boolean',
        'booking_optional' => 'boolean',
        'members_only' => 'boolean',
        'equipments' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'price_mode' => PriceModes::class,
        'use_special_discount' => 'boolean',
        'use_donation' => 'boolean',
        'charter_mode' => CharterModes::class,
        'secret_message' => 'encrypted',
        'secret_message_days_before' => 'integer',
        'embed_calendar_mode' => EmbedCalendarModes::class,
        'calendar_view_mode' => CalendarViewModes::class,
        'external_slot_provider' => ExternalSlotProviders::class,
        'disable_mailer' => 'boolean',
        'allowed_weekdays' => 'array',
    ];

    /** Équipements proposables à la création d'une salle (La Pépite). */
    public const EQUIPMENTS = ['wifi', 'visio', 'screen', 'flipchart', 'sound', 'pmr'];

    /** Libellé traduit d'un équipement. */
    public static function equipmentLabel(string $key): string
    {
        return match ($key) {
            'wifi' => __('Wi-Fi'),
            'visio' => __('Videoconferencing'),
            'screen' => __('Screen / beamer'),
            'flipchart' => __('Flip-chart'),
            'sound' => __('Sound system'),
            'pmr' => __('Wheelchair access'),
            default => $key,
        };
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(RoomAvailabilityWindow::class);
    }

    /**
     * La salle a-t-elle des fenêtres de disponibilité par jour (La Pépite) ?
     */
    public function hasAvailabilityWindows(): bool
    {
        return $this->availabilityWindows()->exists();
    }

    /**
     * Le créneau [start, end] tombe-t-il dans une fenêtre de disponibilité ?
     * RESTRICTION SEULE : sans fenêtre définie → toujours vrai (natif).
     * Avec fenêtres : le créneau doit tenir entièrement dans une fenêtre du
     * bon jour de semaine (même jour, pas de passage à minuit).
     */
    public function isWithinAvailabilityWindows(\Carbon\Carbon $start, \Carbon\Carbon $end): bool
    {
        $windows = $this->relationLoaded('availabilityWindows')
            ? $this->availabilityWindows
            : $this->availabilityWindows()->get();

        if ($windows->isEmpty()) {
            return true;
        }

        $tz = $this->getTimezone();
        $s = $start->copy()->setTimezone($tz);
        $e = $end->copy()->setTimezone($tz);

        $weekday = $s->isoWeekday(); // 1..7
        $dayStart = $s->copy()->startOfDay();
        $startMin = (int) round(($s->timestamp - $dayStart->timestamp) / 60);
        $endMin = (int) round(($e->timestamp - $dayStart->timestamp) / 60);

        // Créneau à cheval sur un autre jour → refusé pour une salle à fenêtres.
        if ($endMin > 24 * 60 || $endMin <= $startMin) {
            return false;
        }

        foreach ($windows->where('weekday', $weekday) as $w) {
            $ws = $this->timeToMinutes($w->start_time);
            $we = $this->timeToMinutes($w->end_time);
            if ($startMin >= $ws && $endMin <= $we) {
                return true;
            }
        }

        return false;
    }

    protected function timeToMinutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }
        $p = explode(':', $time);

        return ((int) ($p[0] ?? 0)) * 60 + ((int) ($p[1] ?? 0));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function openedEveryday(): bool
    {
        return count($this->allowed_weekdays) === 7;
    }

    public function getTimezone(): string
    {
        return app(SettingsService::class)->timezone(room: $this);
    }

    public function getCurrency(): string
    {
        return app(SettingsService::class)->currency($this->owner);
    }

    public function getLocale(): string
    {
        return app(SettingsService::class)->locale($this->owner);
    }

    public function usesCaldav(): bool
    {
        return $this->external_slot_provider === ExternalSlotProviders::CALDAV
            && $this->dav_calendar
            && $this->owner->use_caldav
            && $this->owner->caldavSettings()->valid();
    }

    public function usesWebdav(): bool
    {
        return $this->owner->usesWebdav();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(RoomDiscount::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(RoomOption::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(RoomUnavailability::class);
    }

    /**
     * Users with direct access to this room (via room_user pivot).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * Entreprises ayant accès à cette salle (La Pépite).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function shortPriceRuleLabel()
    {
        if (! $this->price_short || ! $this->max_hours_short) {
            return '';
        }
        $rules = ['≤ '.$this->max_hours_short.'h'];
        if ($this->always_short_before) {
            $rules[] = __('before').' '.$this->always_short_before.'h';
        }
        if ($this->always_short_after) {
            $rules[] = __('after').' '.$this->always_short_after.'h';
        }

        return implode(', ', $rules);
    }

    /**
     * Get the images for this room.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('order');
    }

    /**
     * Check if the room has an address.
     */
    public function hasAddress(): bool
    {
        return $this->street && $this->city;
    }

    /**
     * Get the formatted address.
     */
    public function formattedAddress(): string
    {
        if (! $this->hasAddress()) {
            return '';
        }

        return sprintf(
            '%s, %s %s, %s',
            $this->street,
            $this->postal_code,
            $this->city,
            $this->country
        );
    }

    /**
     * Check if the room has GPS coordinates.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function allowedWeekdayNames(): array
    {
        $weekdayNames = [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
        ];

        return array_map(fn ($d) => $weekdayNames[$d] ?? '', $this->allowed_weekdays);
    }
}
