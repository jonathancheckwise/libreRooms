<?php

namespace App\Services\Reservation;

use App\Enums\DiscountTypes;
use App\Models\Room;
use App\Models\RoomDiscount;
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
        $shortAfter = $room->always_short_after;
        $shortBefore = $room->always_short_before;
        $maxHoursShort = $room->max_hours_short;
        $priceShort = $room->price_short;
        $priceFullDay = $room->price_full_day;
        // Palier demi-journée (La Pépite) : optionnel, entre le court et la journée.
        $priceHalfDay = $room->price_half_day;
        $maxHoursHalfDay = $room->max_hours_half_day;

        $segments = $this->splitByDay($start, $end, $room);

        $nbShort = 0;
        $nbHalf = 0;
        $nbFull = 0;

        foreach ($segments as $segment) {
            $duration = $segment['end'] - $segment['start'];

            $isShort = $priceShort && (
                ($shortBefore && $segment['end'] <= $shortBefore) ||
                ($shortAfter && $segment['start'] >= $shortAfter) ||
                ($maxHoursShort && $duration <= $maxHoursShort)
            );

            if ($isShort) {
                $nbShort++;
            } elseif ($priceHalfDay && $maxHoursHalfDay && $duration <= $maxHoursHalfDay) {
                $nbHalf++;
            } else {
                $nbFull++;
            }
        }

        // Build label
        $label = '';
        if (count($segments) > 1) {
            if ($nbShort) {
                $label .= $nbShort.'x '.__('short booking').', ';
            }
            if ($nbHalf) {
                $label .= $nbHalf.'x '.__('half day booking').', ';
            }
            if ($nbFull) {
                $label .= $nbFull.'x '.__('full day booking').', ';
            }
            $label = substr($label, 0, -2);
        } else {
            if ($nbShort) {
                $label .= __('Short booking');
            } elseif ($nbHalf) {
                $label .= __('Half day booking');
            } elseif ($nbFull) {
                $label .= __('Full day booking');
            }
        }

        $price = $nbShort * $priceShort + $nbHalf * $priceHalfDay + $nbFull * $priceFullDay;

        return [
            'label' => $label,
            'price' => $price,
        ];
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
