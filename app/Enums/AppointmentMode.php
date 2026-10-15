<?php

namespace App\Enums;

/**
 * How an appointment takes place: face to face, over the phone, or online on a computer.
 */
enum AppointmentMode: string
{
    case InPerson = 'in_person';
    case Phone = 'phone';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'In person',
            self::Phone => 'Phone call',
            self::Online => 'Online',
        };
    }
}
