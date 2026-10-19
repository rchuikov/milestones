<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Russian = 'ru';
    case Serbian = 'sr_Latn';

    public static function default(): self
    {
        return self::English;
    }

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Russian => 'Русский',
            self::Serbian => 'Srpski',
        };
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $locale) {
            $options[$locale->value] = $locale->label();
        }

        return $options;
    }
}
