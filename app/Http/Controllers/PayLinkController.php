<?php

namespace App\Http\Controllers;

use App\Actions\Fees\ConfirmOnlinePayment;
use App\Actions\Fees\StartOnlinePayment;
use App\Models\Guardian;
use App\Models\Student;
use App\Support\Finance\FeeStatement;
use App\Support\Payments\Paystack;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The page a fee reminder links to: what one student owes and a "Pay now" button,
 * for whoever the school sent the signed link to. No sign-in needed, so parents who
 * never set a password can still pay.
 */
class PayLinkController extends Controller
{
    public function show(CurrentSchool $currentSchool, string $student): View
    {
        $student = $this->student($student);
        $statement = FeeStatement::for($student);

        return view('pay.show', [
            'school' => $currentSchool->getOrFail(),
            'student' => $student,
            'statement' => $statement,
            'guardian' => $this->guardian($student),
            'canPayOnline' => FeeStatement::canPayOnline(),
        ]);
    }

    public function start(Request $request, string $student, StartOnlinePayment $start): RedirectResponse
    {
        $student = $this->student($student);
        $data = $request->validate(['email' => ['required', 'email', 'max:160']], [
            'email.required' => 'Enter an email address for your Paystack receipt.',
        ]);

        try {
            return redirect()->away($start->forStudent($student, Str::lower($data['email']), $this->guardian($student)));
        } catch (ValidationException $exception) {
            return back()->withInput()->with('status', $exception->validator->errors()->first());
        } catch (RequestException) {
            return back()->withInput()->with('status', 'Online payment is not available right now. Please try again in a few minutes, or pay at the school.');
        }
    }

    /**
     * Paystack sends the payer back here. Check with Paystack before saying anything.
     */
    public function callback(Request $request, CurrentSchool $currentSchool, Paystack $paystack, ConfirmOnlinePayment $confirm): View
    {
        $reference = (string) $request->query('reference');
        $payment = null;

        if (StartOnlinePayment::schoolIdFromReference($reference) === $currentSchool->getOrFail()->id) {
            try {
                $payment = $confirm->handle($reference, $paystack->verify($reference));
            } catch (RequestException) {
                $payment = null;
            }
        }

        return view('pay.done', [
            'school' => $currentSchool->getOrFail(),
            'payment' => $payment,
            'student' => $payment?->student,
        ]);
    }

    private function student(string $ulid): Student
    {
        return Student::query()->with('currentEnrolment.classArm.level')->where('ulid', $ulid)->firstOrFail();
    }

    private function guardian(Student $student): ?Guardian
    {
        return $student->guardians()->orderByDesc('guardian_student.is_primary')->first();
    }
}
