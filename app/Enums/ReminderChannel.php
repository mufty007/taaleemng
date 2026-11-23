<?php

namespace App\Enums;

/**
 * How a fee reminder reached the parent.
 */
enum ReminderChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Link => 'Pay link copied',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'mail',
            self::WhatsApp => 'message-circle',
            self::Sms => 'message-square-text',
            self::Link => 'link',
        };
    }
}
