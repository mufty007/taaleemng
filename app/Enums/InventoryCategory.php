<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case Uniforms = 'uniforms';
    case Textbooks = 'textbooks';
    case Stationery = 'stationery';
    case Lab = 'lab';
    case Sports = 'sports';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Uniforms => 'Uniforms',
            self::Textbooks => 'Textbooks',
            self::Stationery => 'Stationery',
            self::Lab => 'Lab & science',
            self::Sports => 'Sports',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Uniforms => 'shirt',
            self::Textbooks => 'book',
            self::Stationery => 'pencil',
            self::Lab => 'flask-conical',
            self::Sports => 'volleyball',
            self::Other => 'package',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category) => [$category->value => $category->label()])->all();
    }
}
