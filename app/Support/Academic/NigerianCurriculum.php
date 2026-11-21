<?php

namespace App\Support\Academic;

use App\Enums\ClassSection;
use App\Enums\SchoolType;
use App\Enums\SubjectCategory;

/**
 * Default class levels and subjects for Nigerian schools (Universal Basic Education
 * structure: Nursery, Primary 1–6, JSS 1–3, SSS 1–3 with streams). Schools edit from here.
 */
final class NigerianCurriculum
{
    public const STREAMS = ['Science', 'Arts', 'Commercial'];

    /**
     * Which sections each type of school runs.
     *
     * @return list<ClassSection>
     */
    public static function sectionsFor(SchoolType $type): array
    {
        return match ($type) {
            SchoolType::NurseryPrimary, SchoolType::Islamiyya => [ClassSection::Nursery, ClassSection::Primary],
            SchoolType::Secondary => [ClassSection::Junior, ClassSection::Senior],
            SchoolType::Combined => [ClassSection::Nursery, ClassSection::Primary, ClassSection::Junior, ClassSection::Senior],
        };
    }

    /**
     * @return list<array{name: string, short_name: string, section: ClassSection, position: int}>
     */
    public static function levels(ClassSection $section): array
    {
        [$names, $short, $offset] = match ($section) {
            ClassSection::Nursery => [['Nursery 1', 'Nursery 2'], ['NUR 1', 'NUR 2'], 0],
            ClassSection::Primary => [
                ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'],
                ['Pry 1', 'Pry 2', 'Pry 3', 'Pry 4', 'Pry 5', 'Pry 6'],
                10,
            ],
            ClassSection::Junior => [['JSS 1', 'JSS 2', 'JSS 3'], ['JSS 1', 'JSS 2', 'JSS 3'], 20],
            ClassSection::Senior => [['SSS 1', 'SSS 2', 'SSS 3'], ['SSS 1', 'SSS 2', 'SSS 3'], 30],
            ClassSection::Islamiyya => [['Tamhidi', 'Ibtida’i 1', 'Ibtida’i 2', 'Ibtida’i 3'], ['TAM', 'IBT 1', 'IBT 2', 'IBT 3'], 40],
        };

        return array_map(fn (int $index): array => [
            'name' => $names[$index],
            'short_name' => $short[$index],
            'section' => $section,
            'position' => $offset + $index + 1,
        ], array_keys($names));
    }

    /**
     * The subject catalogue. 'streams' limits a senior subject to those streams (null = all).
     * 'islamiyya' subjects are only added for Islamiyya schools; 'language' picks the one Nigerian
     * language a school teaches by default.
     *
     * @return list<array{name: string, code: string, category: SubjectCategory, sections: list<ClassSection>, streams?: list<string>, islamiyya?: bool}>
     */
    public static function subjects(): array
    {
        $n = ClassSection::Nursery;
        $p = ClassSection::Primary;
        $j = ClassSection::Junior;
        $s = ClassSection::Senior;

        return [
            ['name' => 'English Language', 'code' => 'ENG', 'category' => SubjectCategory::Core, 'sections' => [$n, $p, $j, $s]],
            ['name' => 'Mathematics', 'code' => 'MTH', 'category' => SubjectCategory::Core, 'sections' => [$n, $p, $j, $s]],
            ['name' => 'Phonics', 'code' => 'PHO', 'category' => SubjectCategory::Core, 'sections' => [$n]],
            ['name' => 'Handwriting', 'code' => 'HWR', 'category' => SubjectCategory::Core, 'sections' => [$n]],
            ['name' => 'Social Habits', 'code' => 'SHB', 'category' => SubjectCategory::Core, 'sections' => [$n]],
            ['name' => 'Basic Science', 'code' => 'BSC', 'category' => SubjectCategory::Core, 'sections' => [$n, $p, $j]],
            ['name' => 'Basic Technology', 'code' => 'BTE', 'category' => SubjectCategory::Core, 'sections' => [$j]],
            ['name' => 'Social Studies', 'code' => 'SOS', 'category' => SubjectCategory::Core, 'sections' => [$p, $j]],
            ['name' => 'Civic Education', 'code' => 'CVE', 'category' => SubjectCategory::Core, 'sections' => [$p, $j, $s]],
            ['name' => 'Quantitative Reasoning', 'code' => 'QTR', 'category' => SubjectCategory::Core, 'sections' => [$p]],
            ['name' => 'Verbal Reasoning', 'code' => 'VBR', 'category' => SubjectCategory::Core, 'sections' => [$p]],
            ['name' => 'Computer Studies', 'code' => 'CPS', 'category' => SubjectCategory::Core, 'sections' => [$p, $j]],
            ['name' => 'Cultural and Creative Arts', 'code' => 'CCA', 'category' => SubjectCategory::Core, 'sections' => [$n, $p, $j]],
            ['name' => 'Physical and Health Education', 'code' => 'PHE', 'category' => SubjectCategory::Core, 'sections' => [$p, $j]],
            ['name' => 'Agricultural Science', 'code' => 'AGR', 'category' => SubjectCategory::Core, 'sections' => [$p, $j, $s]],
            ['name' => 'Home Economics', 'code' => 'HEC', 'category' => SubjectCategory::Vocational, 'sections' => [$j]],
            ['name' => 'Business Studies', 'code' => 'BUS', 'category' => SubjectCategory::Vocational, 'sections' => [$j]],
            ['name' => 'French', 'code' => 'FRE', 'category' => SubjectCategory::Language, 'sections' => [$p, $j]],
            ['name' => 'Hausa', 'code' => 'HAU', 'category' => SubjectCategory::Language, 'sections' => [$p, $j]],
            ['name' => 'Christian Religious Studies', 'code' => 'CRS', 'category' => SubjectCategory::Religious, 'sections' => [$p, $j, $s]],
            ['name' => 'Islamic Studies', 'code' => 'IRS', 'category' => SubjectCategory::Religious, 'sections' => [$p, $j, $s]],
            ['name' => 'Data Processing', 'code' => 'DAT', 'category' => SubjectCategory::Elective, 'sections' => [$s]],
            ['name' => 'Physics', 'code' => 'PHY', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Science']],
            ['name' => 'Chemistry', 'code' => 'CHM', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Science']],
            ['name' => 'Biology', 'code' => 'BIO', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Science', 'Arts']],
            ['name' => 'Further Mathematics', 'code' => 'FMT', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Science']],
            ['name' => 'Geography', 'code' => 'GEO', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Science', 'Arts']],
            ['name' => 'Literature in English', 'code' => 'LIT', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Arts']],
            ['name' => 'Government', 'code' => 'GOV', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Arts', 'Commercial']],
            ['name' => 'Economics', 'code' => 'ECO', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Commercial', 'Arts']],
            ['name' => 'Financial Accounting', 'code' => 'ACC', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Commercial']],
            ['name' => 'Commerce', 'code' => 'COM', 'category' => SubjectCategory::Elective, 'sections' => [$s], 'streams' => ['Commercial']],
            ['name' => 'Arabic', 'code' => 'ARB', 'category' => SubjectCategory::Language, 'sections' => [$n, $p], 'islamiyya' => true],
            ['name' => 'Qur’an and Tajweed', 'code' => 'QUR', 'category' => SubjectCategory::Religious, 'sections' => [$n, $p], 'islamiyya' => true],
            ['name' => 'Hadith and Fiqh', 'code' => 'HDF', 'category' => SubjectCategory::Religious, 'sections' => [$p], 'islamiyya' => true],
        ];
    }

    /**
     * Subjects for a school type, filtered to the sections it runs.
     *
     * @return list<array{name: string, code: string, category: SubjectCategory, sections: list<ClassSection>, streams?: list<string>, islamiyya?: bool}>
     */
    public static function subjectsFor(SchoolType $type): array
    {
        $sections = self::sectionsFor($type);

        return array_values(array_filter(self::subjects(), function (array $subject) use ($type, $sections): bool {
            if (($subject['islamiyya'] ?? false) && $type !== SchoolType::Islamiyya) {
                return false;
            }

            if ($type === SchoolType::Islamiyya && $subject['code'] === 'CRS') {
                return false;
            }

            return array_intersect(
                array_map(fn (ClassSection $section) => $section->value, $subject['sections']),
                array_map(fn (ClassSection $section) => $section->value, $sections),
            ) !== [];
        }));
    }

    /**
     * Whether a catalogue subject is taught in a class of the given section and stream.
     *
     * @param  array{sections: list<ClassSection>, streams?: list<string>}  $subject
     */
    public static function appliesTo(array $subject, ClassSection $section, ?string $stream): bool
    {
        if (! in_array($section, $subject['sections'], true)) {
            return false;
        }

        return ! isset($subject['streams']) || ($stream !== null && in_array($stream, $subject['streams'], true));
    }
}
