<?php

namespace App\Enums;

/**
 * The look of the birthday card email; each case has its own view in emails/birthdays.
 */
enum BirthdayLayout: string
{
    case Balloons = 'balloons';
    case Confetti = 'confetti';
    case Cake = 'cake';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Balloons => 'Light and cheerful, with pastel balloons.',
            self::Confetti => 'A bright party card bursting with confetti.',
            self::Cake => 'Warm and elegant, with a cake and candles.',
        };
    }

    public function view(): string
    {
        return 'emails.birthdays.'.$this->value;
    }
}
