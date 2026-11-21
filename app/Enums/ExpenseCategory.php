<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Salaries = 'salaries';
    case Power = 'power';
    case Maintenance = 'maintenance';
    case TeachingMaterials = 'teaching_materials';
    case Stationery = 'stationery';
    case Transport = 'transport';
    case Food = 'food';
    case ExamFees = 'exam_fees';
    case Events = 'events';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Salaries => 'Salaries & allowances',
            self::Power => 'Power (NEPA bills & diesel)',
            self::Maintenance => 'Repairs & maintenance',
            self::TeachingMaterials => 'Teaching materials',
            self::Stationery => 'Stationery & printing',
            self::Transport => 'Transport & fuel',
            self::Food => 'Food & kitchen',
            self::ExamFees => 'Exam bodies (WAEC, NECO, BECE)',
            self::Events => 'Events & sports',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Salaries => 'banknote',
            self::Power => 'zap',
            self::Maintenance => 'wrench',
            self::TeachingMaterials => 'book-open',
            self::Stationery => 'printer',
            self::Transport => 'bus',
            self::Food => 'utensils',
            self::ExamFees => 'file-check',
            self::Events => 'trophy',
            self::Other => 'circle-ellipsis',
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
