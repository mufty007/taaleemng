<?php

namespace App\Actions\Results;

use App\Enums\ClassSection;
use App\Enums\CommentAudience;
use App\Enums\TraitCategory;
use App\Models\AssessmentComponent;
use App\Models\ClassLevel;
use App\Models\CommentTemplate;
use App\Models\GradingScheme;
use App\Models\ReportTrait;

/**
 * Nigerian defaults for results: CA1 (20) + CA2 (20) + Exam (60), the WAEC
 * 9-point scale for senior secondary and an A–F scale below it, the usual
 * affective and psychomotor traits, and a starter comment bank. Safe to re-run.
 */
class SetUpAssessment
{
    public const BASIC_BANDS = [
        ['min' => 70, 'grade' => 'A', 'remark' => 'Excellent'],
        ['min' => 60, 'grade' => 'B', 'remark' => 'Very good'],
        ['min' => 50, 'grade' => 'C', 'remark' => 'Good'],
        ['min' => 45, 'grade' => 'D', 'remark' => 'Fair'],
        ['min' => 40, 'grade' => 'E', 'remark' => 'Poor'],
        ['min' => 0, 'grade' => 'F', 'remark' => 'Fail'],
    ];

    public const WAEC_BANDS = [
        ['min' => 75, 'grade' => 'A1', 'remark' => 'Excellent'],
        ['min' => 70, 'grade' => 'B2', 'remark' => 'Very good'],
        ['min' => 65, 'grade' => 'B3', 'remark' => 'Good'],
        ['min' => 60, 'grade' => 'C4', 'remark' => 'Credit'],
        ['min' => 55, 'grade' => 'C5', 'remark' => 'Credit'],
        ['min' => 50, 'grade' => 'C6', 'remark' => 'Credit'],
        ['min' => 45, 'grade' => 'D7', 'remark' => 'Pass'],
        ['min' => 40, 'grade' => 'E8', 'remark' => 'Pass'],
        ['min' => 0, 'grade' => 'F9', 'remark' => 'Fail'],
    ];

    public const TRAITS = [
        'affective' => ['Punctuality', 'Neatness', 'Politeness', 'Honesty', 'Self-control', 'Attentiveness', 'Relationship with others', 'Leadership'],
        'psychomotor' => ['Handwriting', 'Verbal fluency', 'Games & sports', 'Drawing & painting', 'Handling of tools', 'Musical skills'],
    ];

    /**
     * Comments by average band: [min, max, form teacher comments, principal comments].
     */
    public const COMMENTS = [
        [75, 100, [
            'An outstanding result. {name} is hardworking and a joy to teach. Keep it up!',
            'Excellent performance. {name} works with focus and sets a good example in class.',
        ], [
            'An excellent result. We are proud of {name}.',
            'Outstanding performance. Keep soaring, {name}.',
        ]],
        [60, 74.99, [
            'A very good result. {name} is diligent and can reach the top with a little more effort.',
            'Very good work this term. {name} participates well in class.',
        ], [
            'A very good result. Aim higher next term.',
            'Very good performance. Keep working hard.',
        ]],
        [50, 59.99, [
            'A good result. {name} should read more and ask questions in class.',
            'Good effort this term. {name} can do better with more concentration.',
        ], [
            'A good result, but there is room for improvement.',
            'Good. Put in more effort next term.',
        ]],
        [40, 49.99, [
            'A fair result. {name} needs to be more serious with studies and assignments.',
            'Fair performance. {name} should pay more attention in class and revise daily.',
        ], [
            'A fair result. Parents should support {name} with studies at home.',
            'Fair. More effort is needed next term.',
        ]],
        [0, 39.99, [
            '{name} needs to work much harder. Extra lessons and close follow-up are advised.',
            'A weak result. {name} must take studies more seriously.',
        ], [
            'A poor result. Please see the form teacher about extra support for {name}.',
            'Below expectation. {name} must sit up next term.',
        ]],
    ];

    public function handle(): void
    {
        if (! AssessmentComponent::query()->exists()) {
            foreach ([['First CA', 'CA1', 20], ['Second CA', 'CA2', 20], ['Examination', 'Exam', 60]] as $position => [$name, $short, $max]) {
                AssessmentComponent::query()->create(['name' => $name, 'short_name' => $short, 'max_score' => $max, 'position' => $position + 1]);
            }
        }

        $basic = GradingScheme::query()->firstOrCreate(['name' => 'Basic (A–F)'], ['bands' => self::BASIC_BANDS]);
        $waec = GradingScheme::query()->firstOrCreate(['name' => 'WAEC (A1–F9)'], ['bands' => self::WAEC_BANDS]);

        ClassLevel::query()->whereNull('grading_scheme_id')->each(function (ClassLevel $level) use ($basic, $waec): void {
            $level->update(['grading_scheme_id' => $level->section === ClassSection::Senior ? $waec->id : $basic->id]);
        });

        if (! ReportTrait::query()->exists()) {
            foreach (self::TRAITS as $category => $names) {
                foreach ($names as $position => $name) {
                    ReportTrait::query()->create(['name' => $name, 'category' => TraitCategory::from($category), 'position' => $position + 1]);
                }
            }
        }

        if (! CommentTemplate::query()->exists()) {
            foreach (self::COMMENTS as [$min, $max, $teacherComments, $principalComments]) {
                foreach ([CommentAudience::FormTeacher->value => $teacherComments, CommentAudience::Principal->value => $principalComments] as $audience => $bodies) {
                    foreach ($bodies as $body) {
                        CommentTemplate::query()->create(['audience' => $audience, 'min_average' => $min, 'max_average' => $max, 'body' => $body]);
                    }
                }
            }
        }
    }
}
