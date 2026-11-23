<?php

namespace App\Actions\Platform;

use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Create a school tenant with its role templates and first School Admin.
 */
class CreateSchool
{
    /**
     * Slugs that would collide with fixed routes under /app or subdomains.
     */
    public const RESERVED_SLUGS = ['schools', 'platform', 'account', 'admin', 'api', 'app', 'www', 'mail', 'support', 'help'];

    public function __construct(
        private SeedSchoolRoles $seedSchoolRoles,
        private AddSchoolMember $addSchoolMember,
    ) {}

    /**
     * @param  array{name: string, school_type: string|\BackedEnum, state?: ?string, lga?: ?string, phone?: ?string, email?: ?string, slug?: ?string, short_code?: ?string, plan?: mixed, billing_model?: mixed, billing_cycle?: ?string, class_structure?: mixed}  $attributes
     * @param  array{name: string, email: string, password?: string}  $admin
     */
    public function handle(array $attributes, array $admin, bool $sendPasswordReset = true): School
    {
        [$school, $adminUser] = DB::transaction(function () use ($attributes, $admin): array {
            $school = School::query()->create([
                ...$attributes,
                'slug' => $this->uniqueSlug($attributes['slug'] ?? $attributes['name']),
                'short_code' => $this->uniqueShortCode($attributes['short_code'] ?? $attributes['name']),
                'status' => SchoolStatus::Trial,
                'trial_ends_at' => now()->addDays((int) config('taaleem.trial_days')),
            ]);

            $this->seedSchoolRoles->handle($school);

            $adminUser = User::query()->firstOrCreate(
                ['email' => Str::lower($admin['email'])],
                [
                    'name' => $admin['name'],
                    'password' => $admin['password'] ?? Str::password(32),
                ],
            );

            $this->addSchoolMember->handle($school, $adminUser, SchoolRole::SchoolAdmin);

            activity('platform')
                ->performedOn($school)
                ->tap(fn ($activity) => $activity->school_id = $school->id)
                ->log('created school');

            return [$school, $adminUser];
        });

        if ($sendPasswordReset && $adminUser->wasRecentlyCreated && blank($admin['password'] ?? null)) {
            Password::broker()->sendResetLink(['email' => $adminUser->email]);
        }

        return $school;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::limit(Str::slug($value), 50, '') ?: 'school';
        $slug = $base;

        for ($suffix = 2; in_array($slug, self::RESERVED_SLUGS, true) || School::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function uniqueShortCode(string $value): string
    {
        $words = Str::of($value)->replaceMatches('/[^A-Za-z\s]/', ' ')->squish()->explode(' ')->filter();

        $base = $words->count() > 1
            ? $words->map(fn (string $word): string => Str::substr($word, 0, 1))->take(5)->implode('')
            : Str::substr($words->first() ?? 'SCH', 0, 4);

        $base = Str::upper($base) ?: 'SCH';
        $code = $base;

        for ($suffix = 2; School::withTrashed()->where('short_code', $code)->exists(); $suffix++) {
            $code = $base.$suffix;
        }

        return $code;
    }
}
