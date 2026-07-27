<?php

namespace App\Services\Reservation;

use App\Enums\DiscountTypes;
use App\Models\Room;
use App\Models\RoomDiscount;
use App\Models\SystemSettings;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;

class PricingService
{
    /**
     * Split event by day considering late end continuation
     * Returns array of segments with start/end hours and date
     */
    protected function splitByDay(Carbon $start, Carbon $end, Room $room): array
    {
        $timezone = app(SettingsService::class)->timezone($room);
        $segments = [];
        $allowLateEnd = $room->allow_late_end_hour;

        // Convert to room timezone for day splitting
        $startInTimezone = $start->copy()->setTimezone($timezone);
        $endInTimezone = $end->copy()->setTimezone($timezone);

        $startDay = $startInTimezone->copy()->startOfDay();
        $endDay = $endInTimezone->copy()->startOfDay();

        // Check if crosses midnight
        $crossesMidnight = ! $startDay->eq($endDay);

        // Extract hours with decimals
        $startHour = $startInTimezone->hour + $startInTimezone->minute / 60;
        $endHour = $endInTimezone->hour + $endInTimezone->minute / 60;

        // Check for late continuation
        $hasLateContinuation = $crossesMidnight && $endHour <= $allowLateEnd;

        // Iterate over each day
        $current = $startDay->copy();

        while ($current->lte($endDay)) {
            $isFirst = $current->eq($startDay);
            $isLast = $current->eq($endDay);

            $segments[] = [
                'start' => $isFirst ? $startHour : 0,
                'end' => $isLast ? $endHour : 24,
                'date' => $current->toDateString(),
            ];

            $current->addDay();
        }

        // Apply late continuation: remove last segment and extend previous one
        if ($hasLateContinuation) {
            array_pop($segments);
            $segments[count($segments) - 1]['end'] = 24 + $endHour;
        }

        return $segments;
    }

    /**
     * Calculate price and label for an event (without options)
     * Returns ['label' => string, 'price' => float]
     */
    public function calculateEventPrice(Carbon $start, Carbon $end, Room $room, ?string $orgType = null): array
    {
        // Modèle La Pépite : chaque segment est classé selon les CRÉNEAUX GLOBAUX
        // (réglages système), et facturé au PRIX DE LA SALLE correspondant.
        // La colonne de prix dépend du statut du réservant (sans/avec but
        // lucratif). La remise membre -10 % est appliquée séparément au total.
        $settings = app(SystemSettings::class);
        $hourlyMax = (float) $settings->hourly_max_hours;
        $morningStart = $this->timeToHours($settings->half_day_morning_start);
        $morningEnd = $this->timeToHours($settings->half_day_morning_end);
        $afternoonStart = $this->timeToHours($settings->half_day_afternoon_start);
        $afternoonEnd = $this->timeToHours($settings->half_day_afternoon_end);
        $eveningStart = $this->timeToHours($settings->half_day_evening_start);
        $eveningEnd = $this->timeToHours($settings->half_day_evening_end);
        $fullStart = $this->timeToHours($settings->full_day_start);
        $fullEnd = $this->timeToHours($settings->full_day_end);

        // Prix propres à la salle, colonne choisie selon le type d'organisme.
        $priceHourly = $this->tierPrice($room, $orgType, 'hourly');
        $priceHalfDay = $this->tierPrice($room, $orgType, 'half_day');
        $priceFullDay = $this->tierPrice($room, $orgType, 'full_day');

        $segments = $this->splitByDay($start, $end, $room);

        $match = fn ($a, $b) => abs($a - $b) < 0.02;
        $isWindow = fn ($s, $e, $ws, $we) => $match($s, $ws) && $match($e, $we);

        $price = 0.0;
        $parts = [];
        $hourlyMinutes = 0.0; // minutes facturées au tarif horaire (pour l'heure offerte membre)

        foreach ($segments as $segment) {
            $s = $segment['start'];
            $e = $segment['end'];
            $duration = $e - $s;

            if ($priceFullDay !== null && $isWindow($s, $e, $fullStart, $fullEnd)) {
                $price += $priceFullDay;
                $parts[] = __('Full day booking');
            } elseif ($priceHalfDay !== null && $isWindow($s, $e, $morningStart, $morningEnd)) {
                $price += $priceHalfDay;
                $parts[] = __('Morning half-day');
            } elseif ($priceHalfDay !== null && $isWindow($s, $e, $afternoonStart, $afternoonEnd)) {
                $price += $priceHalfDay;
                $parts[] = __('Afternoon half-day');
            } elseif ($priceHalfDay !== null && $isWindow($s, $e, $eveningStart, $eveningEnd)) {
                $price += $priceHalfDay;
                $parts[] = __('Evening half-day');
            } elseif ($priceHourly !== null && $duration <= $hourlyMax + 0.001) {
                $hours = round($duration, 2);
                $price += $priceHourly * $hours;
                $hourlyMinutes += $duration * 60;
                $parts[] = __('Hourly booking').' ('.$this->formatHours($hours).'h)';
            } elseif ($priceFullDay !== null) {
                // Repli : créneau non reconnu -> tarif journée
                $price += $priceFullDay;
                $parts[] = __('Full day booking');
            }
        }

        // Libellé agrégé (compte par type)
        $counts = array_count_values($parts);
        $labelParts = [];
        foreach ($counts as $name => $n) {
            $labelParts[] = $n > 1 ? $n.'× '.$name : $name;
        }

        return [
            'label' => implode(', ', $labelParts),
            'price' => $price,
            'hourly_minutes' => $hourlyMinutes,
        ];
    }

    /**
     * Convertit "HH:MM" (ou "HH:MM:SS") en heures décimales.
     */
    protected function timeToHours(?string $time): float
    {
        if (! $time) {
            return 0.0;
        }
        $p = explode(':', $time);

        return (int) ($p[0] ?? 0) + ((int) ($p[1] ?? 0)) / 60;
    }

    /**
     * Formate un nombre d'heures sans zéros inutiles (3, 1.5, 2.25).
     */
    protected function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    /**
     * Prix d'une salle pour un mode ('hourly'|'half_day'|'full_day'), selon le
     * type d'organisme du réservant. Les organismes sans but lucratif utilisent
     * la colonne price_np_* ; à défaut (colonne vide) on retombe sur le tarif
     * standard (à but lucratif).
     */
    protected function tierPrice(Room $room, ?string $orgType, string $mode): ?int
    {
        if ($orgType === 'non_profit') {
            return $room->{"price_np_$mode"} ?? $room->{"price_$mode"};
        }

        return $room->{"price_$mode"};
    }

    /**
     * Remise membre La Pépite (-10 % par défaut) sur le prix de base.
     * Retourne une ligne de remise [id, libellé, montant] ou null.
     */
    public function memberDiscount(bool $isMember, float $basePrice): ?array
    {
        if (! $isMember || $basePrice <= 0) {
            return null;
        }

        $pct = (int) app(SystemSettings::class)->member_discount_percent;
        if ($pct <= 0) {
            return null;
        }

        $amount = round($basePrice * $pct / 100, 2);

        return [0, __('Member discount (:pct%)', ['pct' => $pct]), $amount];
    }

    /** Tarif horaire de la salle pour le tier du réservant (ou null). */
    public function hourlyRate(Room $room, ?string $orgType): ?int
    {
        return $this->tierPrice($room, $orgType, 'hourly');
    }

    /**
     * Minutes offertes encore disponibles ce mois-là pour un membre.
     * Quota mensuel = 60 min, décompté sur ses réservations non annulées
     * ayant un événement dans le mois. On peut exclure une réservation
     * (celle en cours d'édition) pour éviter de la compter deux fois.
     */
    public function memberFreeMinutesRemaining(?int $userId, Carbon $monthAnchor, ?int $excludeReservationId = null): int
    {
        if (! $userId) {
            return 0;
        }

        $start = $monthAnchor->copy()->startOfMonth();
        $end = $monthAnchor->copy()->endOfMonth();

        $used = \App\Models\Reservation::query()
            ->where('booked_by_user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->whereHas('events', fn ($q) => $q->whereBetween('start', [$start, $end]))
            ->sum('free_minutes_applied');

        return max(0, 60 - (int) $used);
    }

    /**
     * Calcule l'heure offerte membre pour une réservation à l'heure.
     * Retourne [minutesOffertes, ligneRemise|null].
     */
    public function memberFreeHour(bool $isMember, ?int $hourlyRate, float $hourlyMinutes, int $freeMinutesAvailable): array
    {
        if (! $isMember || ! $hourlyRate || $hourlyMinutes <= 0 || $freeMinutesAvailable <= 0) {
            return [0, null];
        }

        $freeMinutes = (int) min(60, $freeMinutesAvailable, $hourlyMinutes);
        if ($freeMinutes <= 0) {
            return [0, null];
        }

        $amount = round($hourlyRate * $freeMinutes / 60, 2);

        return [$freeMinutes, [0, __('Free hour (member)'), $amount]];
    }

    /**
     * Calculate price and label for options
     * Returns ['label' => string, 'price' => float]
     */
    public function calculateOptionsPrice(array $optionIds, Room $room): array
    {
        if (empty($optionIds)) {
            return ['label' => '', 'price' => 0];
        }

        $label = count($optionIds) === 1 ? __('option').': ' : __('options').': ';
        $price = 0;

        foreach ($optionIds as $optionId) {
            $option = $room->options->firstWhere('id', $optionId);
            if ($option) {
                $label .= $option->name.', ';
                $price += $option->price;
            }
        }

        $label = substr($label, 0, -2);

        return [
            'label' => $label,
            'price' => $price,
        ];
    }

    /**
     * @return array{0: float, 1: array<int, array{0: int, 1: string, 2: float}>}
     */
    public function calculateSumDiscounts(Room $room, array $discountIds, float $fullPrice): array
    {
        $sumDiscounts = 0;
        $discountsData = [];
        if (! empty($discountIds)) {
            foreach ($room->discounts as $discount) {
                if (in_array($discount->id, $discountIds)) {
                    $amount = $this->calculateDiscountValue($discount, $fullPrice);
                    $sumDiscounts += $amount;
                    $discountsData[] = [$discount->id, $discount->name, round($amount, 2)];
                }
            }
        }

        return [$sumDiscounts, $discountsData];
    }

    /**
     * Calculate discount value based on type
     */
    protected function calculateDiscountValue(RoomDiscount $discount, float $initPrice): float
    {
        return match ($discount->type) {
            DiscountTypes::FIXED => $discount->value,
            DiscountTypes::PERCENT => $discount->value * $initPrice / 100,
            default => 0,
        };
    }
}
