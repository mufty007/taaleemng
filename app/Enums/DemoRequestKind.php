<?php

namespace App\Enums;

enum DemoRequestKind: string
{
    case Trial = 'trial';
    case Demo = 'demo';
    case Question = 'question';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Free trial',
            self::Demo => 'Book a demo',
            self::Question => 'Question',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::Trial => 'Start my free trial',
            self::Demo => 'Book my demo',
            self::Question => 'Send message',
        };
    }

    public function messagePlaceholder(): string
    {
        return match ($this) {
            self::Trial => 'Anything we should know before setting up your school?',
            self::Demo => 'When suits you for a 30-minute call?',
            self::Question => 'How can we help?',
        };
    }
}
