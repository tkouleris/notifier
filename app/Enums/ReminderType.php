<?php

namespace App\Enums;

/**
 * What kind of notification this is: a one-off schedule of dates, a birthday card sent every year,
 * or an appointment with other people.
 */
enum ReminderType: string
{
    case Standard = 'standard';
    case Birthday = 'birthday';
    case Appointment = 'appointment';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Notification',
            self::Birthday => 'Birthday',
            self::Appointment => 'Appointment',
        };
    }
}
