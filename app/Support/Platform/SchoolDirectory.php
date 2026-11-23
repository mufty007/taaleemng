<?php

namespace App\Support\Platform;

use App\Enums\SchoolStatus;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Schools across the whole platform for the console, with per-school counts worked out
 * in one query (tenant scopes don't apply here: the console sees every school).
 */
final class SchoolDirectory
{
    /** @var array<string, string> */
    public const SORTS = [
        'newest' => 'Newest first',
        'name' => 'Name A–Z',
        'students' => 'Most students',
        'active' => 'Recently active',
    ];

    /**
     * @return Builder<School>
     */
    public static function query(): Builder
    {
        return School::query()
            ->select('schools.*')
            ->addSelect([
                'students_count' => self::activeStudents(),
                'staff_count' => DB::table('staff')->selectRaw('count(*)')
                    ->whereColumn('staff.school_id', 'schools.id')
                    ->where('staff.status', StaffStatus::Active->value)
                    ->whereNull('staff.deleted_at'),
                'last_active_at' => DB::table('school_user')->selectRaw('max(last_active_at)')
                    ->whereColumn('school_user.school_id', 'schools.id'),
            ])
            ->withCasts(['last_active_at' => 'datetime', 'students_count' => 'integer', 'staff_count' => 'integer']);
    }

    /**
     * @param  array{search?: string, status?: ?SchoolStatus, state?: string, sort?: string}  $filters
     * @return Builder<School>
     */
    public static function filtered(array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');
        $sort = $filters['sort'] ?? 'newest';

        return self::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('short_code', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, SchoolStatus $status) => $query->where('status', $status))
            ->when(filled($filters['state'] ?? null), fn (Builder $query) => $query->where('state', $filters['state']))
            ->when($sort === 'name', fn (Builder $query) => $query->orderBy('name'))
            ->when($sort === 'students', fn (Builder $query) => $query->orderByDesc('students_count'))
            ->when($sort === 'active', fn (Builder $query) => $query->orderByRaw('last_active_at is null')->orderByDesc('last_active_at'))
            ->when(! in_array($sort, ['name', 'students', 'active'], true), fn (Builder $query) => $query->latest());
    }

    /**
     * How many schools have each status.
     *
     * @return array<string, int>
     */
    public static function statusCounts(): array
    {
        $counts = School::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(SchoolStatus::cases())
            ->mapWithKeys(fn (SchoolStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Active students across every school.
     */
    public static function totalStudents(): int
    {
        return DB::table('students')->where('status', StudentStatus::Active->value)->whereNull('deleted_at')->count();
    }

    private static function activeStudents(): QueryBuilder
    {
        return DB::table('students')->selectRaw('count(*)')
            ->whereColumn('students.school_id', 'schools.id')
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at');
    }
}
