<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A quote is good for $quoteDays days from $today, but never past the earliest end date given
 * (end dates are inclusive, Y-m-d). $days is negative once an end date has passed.
 */
class QuoteValidity
{
    public static function for(string $today, array $endDates, int $quoteDays = 30): array
    {
        $start = Carbon::parse($today)->startOfDay();
        $quoteEnd = $start->copy()->addDays($quoteDays);
        $earliest = collect($endDates)->filter()->map(fn ($d) => Carbon::parse($d)->startOfDay())->min();
        $capped = $earliest !== null && $earliest->lt($quoteEnd);
        $until = $capped ? $earliest : $quoteEnd;

        return ['until' => $until, 'days' => (int) $start->diffInDays($until, false), 'capped' => $capped];
    }
}
