<?php

namespace App\Actions\Academic;

use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Exactly one term (and its session) is current per school. Everything that
 * defaults to "this term" (scores, attendance, invoices) reads it.
 */
class MakeTermCurrent
{
    public function handle(Term $term): void
    {
        DB::transaction(function () use ($term): void {
            Term::query()->where('is_current', true)->update(['is_current' => false]);
            AcademicSession::query()->where('is_current', true)->update(['is_current' => false]);

            $term->update(['is_current' => true]);
            $term->academicSession()->update(['is_current' => true]);
        });

        $term->loadMissing('academicSession');

        activity('academics')->performedOn($term)->log("made {$term->name} {$term->academicSession->name} the current term");
    }
}
