<?php

namespace App\Support\Schools;

use App\Enums\SchoolRole;
use App\Models\School;
use App\Models\User;

/**
 * Short first-login tips for each role. Schools can rewrite the copy in Settings.
 */
final class RoleOnboarding
{
    /**
     * @return array<string, array{title: string, steps: list<string>}>
     */
    public static function defaults(): array
    {
        return [
            SchoolRole::SchoolAdmin->value => [
                'title' => 'Welcome, school admin',
                'steps' => [
                    'Finish the setup checklist on your dashboard.',
                    'Invite the principal, bursar and teachers from Staff.',
                    'Check Settings for fee payouts and your Ta’aleem plan.',
                ],
            ],
            SchoolRole::Principal->value => [
                'title' => 'Welcome, principal',
                'steps' => [
                    'Review today’s registers and results on the dashboard.',
                    'Publish notices from the notice board when you need parents and staff to know something.',
                    'Open Classes to see each form teacher and their subjects.',
                ],
            ],
            SchoolRole::Bursar->value => [
                'title' => 'Welcome, bursar',
                'steps' => [
                    'Set fee items for this term under Fees.',
                    'Record payments as parents pay, or let them pay online.',
                    'Use Debtors to chase balances before exams.',
                ],
            ],
            SchoolRole::Teacher->value => [
                'title' => 'Welcome, teacher',
                'steps' => [
                    'Take today’s register for your form class.',
                    'Enter scores and set classwork from your subjects.',
                    'Check My timetable for today’s lessons.',
                ],
            ],
            SchoolRole::Parent->value => [
                'title' => 'Welcome, parent',
                'steps' => [
                    'Open each child’s card to see results, fees and attendance.',
                    'Pay open fees online when the school has payouts connected.',
                    'Read the notice board for school announcements.',
                ],
            ],
            SchoolRole::Student->value => [
                'title' => 'Welcome',
                'steps' => [
                    'Check Results when your class report is published.',
                    'Open My work for assignments and quizzes.',
                    'Use the notice board for exam timetables and school news.',
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, steps: list<string>}|null
     */
    public static function for(User $user, School $school): ?array
    {
        $role = $user->getRoleNames()->first();

        if ($role === null || ! isset(self::defaults()[$role])) {
            return null;
        }

        $custom = is_array($school->role_onboarding[$role] ?? null) ? $school->role_onboarding[$role] : [];

        if (($custom['enabled'] ?? true) === false) {
            return null;
        }

        $default = self::defaults()[$role];

        return [
            'title' => (string) ($custom['title'] ?? $default['title']),
            'steps' => array_values(array_filter(array_map('strval', $custom['steps'] ?? $default['steps']))),
        ];
    }
}
