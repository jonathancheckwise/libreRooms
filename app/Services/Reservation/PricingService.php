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
    public function calculateEventPrice(Carbon $start, Carbon $end, Room $room): array
    {
        // Modèle La Pépite : chaque segment est classé selon les CRÉNEAUX GLOBAUX
        // (réglages système), et facturé au PRIX DE LA SALLE correspondant.
        $settings = app(SystemSettings::class);
        $hourlyMax = (float) $settings->hourly_max_hours;
        $morningStart = $this->timeToHours($settings->half_day_morning_start);
        $morningEnd = $this->timeToHours($settings->half_day_morning_end);
        $afternoonStart = $this->timeToHours($settings->half_day_afternoon_start);
        $afternoonEnd = $this->timeToHours($settings->half_day_afternoon_end);
        $fullStart = $this->timeToHours($settings->full_day_start);
        $fullEnd = $this->timeToHours($settings->full_day_end);

        // Prix propres à la salle
        $priceHourly = $room->price_hourly;
        $priceHalfDay = $room->price_half_day;
        $priceFullDay = $room->price_full_day;

        $segments = $this->splitByDay($start, $end, $room);

        $match = fn ($a, $b) => abs($a - $b) < 0.02;

        $price = 0.0;
        $parts = [];

        foreach ($segments as $segment) {
            $s = $segment['start'];
            $e = $segment['end'];
            $duration = $e - $s;

            if ($priceFullDay !== null && $match($s, $fullStart) && $match($e, $fullEnd)) {
                $price += $priceFullDay;
                $parts[] = __('Full day booking');
            } elseif ($priceHalfDay !== null && $match($s, $morningStart) && $match($e, $morningEnd)) {
                $price += $priceHalfDay;
                $parts[] = __('Morning half-day');
            } elseif ($priceHalfDay !== null && $match($s, $afternoonStart) && $match($e, $afternoonEnd)) {
                $price += $priceHalfDay;
                $parts[] = __('Afternoon half-day');
            } elseif ($priceHourly !== null && $duration <= $hourlyMax + 0.001) {
                $hours = round($duration, 2);
                $price += $priceHourly * $hours;
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
