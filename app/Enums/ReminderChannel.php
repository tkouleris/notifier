<?php

namespace App\Enums;

/**
 * How a reminder reaches the user. To add SMS later: add `case Sms = 'sms';`,
 * map it to a notification driver below (e.g. 'vonage') and route it on the User model.
 */
enum ReminderChannel: string
{
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
        };
    }

    /**
     * The Laravel notification channel that delivers this option.
     */
    public function driver(): string
    {
        return match ($this) {
            self::Email => 'mail',
        };
    }
}
