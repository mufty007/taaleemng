<?php

namespace App\Http\Controllers\School;

use App\Enums\InvoiceStatus;
use App\Enums\ReminderChannel;
use App\Http\Controllers\Controller;
use App\Models\FeeReminder;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\FeeReminderNotification;
use App\Support\Finance\FeeStatement;
use App\Support\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Remind parents about unpaid fees, with a link to pay online.
 *
 * Email goes out from here. WhatsApp opens on the sender's own phone with the
 * message written for them; this only records that they sent it, so the debtors list
 * can show who has been reminded and when.
 */
class FeeReminderController extends Controller
{
    /** Bulk email skips families already emailed within this many hours. */
    public const QUIET_HOURS = 24;

    public function store(Request $request, CurrentSchool $currentSchool, Student $student): RedirectResponse|JsonResponse
    {
        $channel = ReminderChannel::tryFrom((string) $request->input('channel'));
        abort_if($channel === null, 422);

        if ($channel === ReminderChannel::Sms) {
            return $this->reply($request, 'SMS reminders are coming soon. Use WhatsApp or email for now.', 422);
        }

        $statement = FeeStatement::for($student);

        if ($statement->owed() <= 0) {
            return $this->reply($request, "{$student->fullName()} has no fees outstanding.", 422);
        }

        $guardian = $this->guardian($student);

        if ($channel === ReminderChannel::Email) {
            if (blank($guardian?->email)) {
                return $this->reply($request, "There is no email address for {$student->fullName()}’s parent. Send the reminder by WhatsApp instead.", 422);
            }

            $this->email($currentSchool->getOrFail(), $student, $guardian, $statement);
        }

        $this->record($student, $guardian, $channel, $statement->owed(), $request->user());

        return $this->reply($request, $channel === ReminderChannel::Email
            ? "Reminder emailed to {$guardian?->fullName()}."
            : "{$channel->label()} reminder to {$student->fullName()}’s parent noted.");
    }

    /**
     * Email every family on the debtors list (or one class) who has an email address
     * and was not emailed in the last day.
     */
    public function bulk(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $armId = $request->integer('class') ?: null;
        $school = $currentSchool->getOrFail();

        $students = Student::query()
            ->whereHas('invoices', fn ($query) => $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid]))
            ->when($armId, fn ($query) => $query->whereHas('currentEnrolment', fn ($query) => $query->where('class_arm_id', $armId)))
            ->with(['guardians' => fn ($query) => $query->orderByDesc('guardian_student.is_primary')])
            ->get();

        $recentlyEmailed = FeeReminder::query()
            ->where('channel', ReminderChannel::Email)
            ->where('created_at', '>=', now()->subHours(self::QUIET_HOURS))
            ->pluck('student_id')
            ->flip();

        $sent = 0;
        $noEmail = 0;
        $skipped = 0;

        foreach ($students as $student) {
            if ($recentlyEmailed->has($student->id)) {
                $skipped++;

                continue;
            }

            $statement = FeeStatement::for($student);
            $guardian = $student->guardians->first();

            if ($statement->owed() <= 0) {
                continue;
            }

            if (blank($guardian?->email)) {
                $noEmail++;

                continue;
            }

            $this->email($school, $student, $guardian, $statement);
            $this->record($student, $guardian, ReminderChannel::Email, $statement->owed(), $request->user());
            $sent++;
        }

        if ($sent > 0) {
            activity('fees')->log("emailed fee reminders to {$sent} ".Str::plural('family', $sent));
        }

        return back()->with('status', collect([
            $sent > 0 ? "Emailed {$sent} ".Str::plural('parent', $sent).'.' : 'No reminders emailed.',
            $noEmail > 0 ? "{$noEmail} ".Str::plural('family', $noEmail).' have no email address: use WhatsApp for them.' : null,
            $skipped > 0 ? "{$skipped} already emailed in the last day." : null,
        ])->filter()->join(' '));
    }

    private function email(School $school, Student $student, ?Guardian $guardian, FeeStatement $statement): void
    {
        Notification::route('mail', [(string) $guardian?->email => $guardian?->fullName()])
            ->notify(new FeeReminderNotification(
                schoolName: $school->name,
                studentName: $student->fullName(),
                amount: Money::format($statement->owed()),
                payLink: $statement->payLink(),
                parentName: $guardian ? trim(($guardian->title ? $guardian->title.' ' : '').$guardian->last_name) : null,
                canPayOnline: FeeStatement::canPayOnline(),
                linkDays: FeeStatement::LINK_DAYS,
                schoolEmail: $school->email,
            ));
    }

    private function record(Student $student, ?Guardian $guardian, ReminderChannel $channel, int $owed, User $by): void
    {
        FeeReminder::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian?->id,
            'channel' => $channel,
            'amount' => $owed,
            'sent_by' => $by->id,
        ]);
    }

    private function guardian(Student $student): ?Guardian
    {
        return $student->guardians()->orderByDesc('guardian_student.is_primary')->first();
    }

    private function reply(Request $request, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('status', $message);
    }
}
