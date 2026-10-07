<?php

namespace App\Enums;

enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The markdown mail theme (resources/views/vendor/mail/html/themes) for this look.
     */
    public function mailTheme(): string
    {
        return 'notifier-'.$this->value;
    }
}
