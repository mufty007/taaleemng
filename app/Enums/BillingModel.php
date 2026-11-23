<?php

namespace App\Enums;

/**
 * How a school pays Ta'aleem: a flat plan fee, or per active student.
 */
enum BillingModel: string
{
    case Flat = 'flat';
    case PerStudent = 'per_student';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat fee',
            self::PerStudent => 'Per student',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $model) => [$model->value => $model->label()])->all();
    }
}
