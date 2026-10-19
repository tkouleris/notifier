<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * How often a standard notification comes round again after its final date.
 */
enum Repeat: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Every week',
            self::Monthly => 'Every month',
            self::Yearly => 'Every year',
        };
    }

    /**
     * The nth occurrence after the first one; month ends are kept (January 31 → February 28 → March 31).
     */
    public function occurrence(Carbon $first, int $n): Carbon
    {
        return match ($this) {
            self::Weekly => $first->copy()->addWeeks($n),
            self::Monthly => $first->copy()->addMonthsNoOverflow($n),
            self::Yearly => $first->copy()->addYearsNoOverflow($n),
        };
    }

    /**
     * The longest reminder allowed in the given unit, or null when the unit doesn't fit.
     * Reminders stay shorter than the shortest period, so they never reach back past the previous occurrence.
     */
    public function maxOffset(OffsetUnit $unit): ?int
    {
        $limits = match ($this) {
            self::Weekly => [OffsetUnit::Day->value => 6],
            self::Monthly => [OffsetUnit::Day->value => 27, OffsetUnit::Week->value => 3],
            self::Yearly => [OffsetUnit::Day->value => 364, OffsetUnit::Week->value => 51, OffsetUnit::Month->value => 11],
        };

        return $limits[$unit->value] ?? null;
    }
}
