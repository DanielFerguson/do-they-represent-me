<?php

namespace App\Enums;

/**
 * What a message sent through the contact form is about.
 */
enum ContactTopic: string
{
    case Correction = 'correction';
    case Media = 'media';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'A mistake or correction',
            self::Media => 'Media',
            self::General => 'Something else',
        };
    }
}
