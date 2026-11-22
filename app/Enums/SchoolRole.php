<?php

namespace App\Enums;

/**
 * Role templates seeded into every school. Schools may clone and adjust them,
 * but these system roles always exist so the app can rely on them.
 */
enum SchoolRole: string
{
    case SchoolAdmin = 'school-admin';
    case Principal = 'principal';
    case HeadTeacher = 'head-teacher';
    case Bursar = 'bursar';
    case Teacher = 'teacher';
    case StoreKeeper = 'store-keeper';
    case Student = 'student';
    case Parent = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::SchoolAdmin => 'School Admin',
            self::Principal => 'Principal',
            self::HeadTeacher => 'Head Teacher',
            self::Bursar => 'Bursar / Accountant',
            self::Teacher => 'Teacher',
            self::StoreKeeper => 'Store Keeper / Librarian',
            self::Student => 'Student',
            self::Parent => 'Parent / Guardian',
        };
    }

    /**
     * Whether this role signs in to the staff app rather than the parent/student portal.
     */
    public function isStaff(): bool
    {
        return ! in_array($this, [self::Student, self::Parent], true);
    }
}
