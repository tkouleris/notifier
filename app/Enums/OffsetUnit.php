<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * The unit of a repeating notification's reminder, counted back from each occurrence.
 */
enum OffsetUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(int $amount = 2): string
    {
        return $amount.' '.$this->value.($amount === 1 ? '' : 's');
    }

    public function subtractFrom(Carbon $date, int $amount): Carbon
    {
        return match ($this) {
            self::Day => $date->copy()->subDays($amount),
            self::Week => $date->copy()->subWeeks($amount),
            self::Month => $date->copy()->subMonthsNoOverflow($amount),
        };
    }
}
