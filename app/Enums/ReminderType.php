<?php

namespace App\Enums;

/**
 * What kind of notification this is: a one-off schedule of dates, or a birthday card sent every year.
 */
enum ReminderType: string
{
    case Standard = 'standard';
    case Birthday = 'birthday';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Notification',
            self::Birthday => 'Birthday',
        };
    }
}
