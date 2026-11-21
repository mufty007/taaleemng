<?php

namespace App\Actions\Academic;

use App\Enums\TermStatus;
use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create an academic session (e.g. 2026/2027) with its three terms on typical Nigerian dates:
 * First Term from mid-September, Second from early January, Third from late April.
 * Schools adjust the dates afterwards.
 */
class StartSession
{
    public function __construct(private MakeTermCurrent $makeTermCurrent) {}

    public function handle(int $startYear, bool $makeCurrent = false): AcademicSession
    {
        $name = $startYear.'/'.($startYear + 1);

        if (AcademicSession::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['start_year' => "The {$name} session already exists."]);
        }

        return DB::transaction(function () use ($startYear, $name, $makeCurrent): AcademicSession {
            $terms = self::defaultTermDates($startYear);

            $session = AcademicSession::query()->create([
                'name' => $name,
                'starts_on' => $terms[1]['starts_on'],
                'ends_on' => $terms[3]['ends_on'],
            ]);

            foreach ($terms as $sequence => $dates) {
                $session->terms()->create([
                    'sequence' => $sequence,
                    'name' => Term::NAMES[$sequence],
                    'status' => TermStatus::Open,
                    ...$dates,
                ]);
            }

            activity('academics')->performedOn($session)->log("started the {$name} session");

            if ($makeCurrent) {
                $this->makeTermCurrent->handle($session->terms()->firstOrFail());
            }

            return $session->load('terms');
        });
    }

    /**
     * @return array<int, array{starts_on: Carbon, ends_on: Carbon, mid_term_starts_on: Carbon, mid_term_ends_on: Carbon, next_term_begins_on: Carbon}>
     */
    public static function defaultTermDates(int $startYear): array
    {
        $firstTerm = self::mondayOnOrAfter(Carbon::create($startYear, 9, 8));
        $secondTerm = self::mondayOnOrAfter(Carbon::create($startYear + 1, 1, 5));
        $thirdTerm = self::mondayOnOrAfter(Carbon::create($startYear + 1, 4, 22));
        $nextSession = self::mondayOnOrAfter(Carbon::create($startYear + 1, 9, 8));

        $term = fn (Carbon $start, int $weeks, Carbon $next): array => [
            'starts_on' => $start,
            'ends_on' => $start->copy()->addWeeks($weeks - 1)->addDays(4),
            'mid_term_starts_on' => $start->copy()->addWeeks(6)->addDays(3),
            'mid_term_ends_on' => $start->copy()->addWeeks(7),
            'next_term_begins_on' => $next,
        ];

        return [
            1 => $term($firstTerm, 14, $secondTerm),
            2 => $term($secondTerm, 13, $thirdTerm),
            3 => $term($thirdTerm, 13, $nextSession),
        ];
    }

    private static function mondayOnOrAfter(Carbon $date): Carbon
    {
        return $date->isMonday() ? $date : $date->next(Carbon::MONDAY);
    }
}
