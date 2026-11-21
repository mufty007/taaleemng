<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Admissions\ApplicationFormController;
use App\Http\Controllers\AppEntryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Marketing\DemoRequestController;
use App\Http\Controllers\Marketing\MarketingController;
use App\Http\Controllers\Platform;
use App\Http\Controllers\Portal;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\School;
use App\Http\Controllers\SchoolPickerController;
use App\Http\Controllers\Webhooks\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing site
|--------------------------------------------------------------------------
*/

Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('/contact', [MarketingController::class, 'contact'])->name('contact');
Route::get('/privacy', [MarketingController::class, 'legal'])->defaults('page', 'privacy')->name('privacy');
Route::get('/terms', [MarketingController::class, 'legal'])->defaults('page', 'terms')->name('terms');
Route::post('/demo-requests', [DemoRequestController::class, 'store'])
    ->middleware('throttle:demo-requests')
    ->name('demo-requests.store');

// A school's public admission form.
Route::middleware('school.public')->prefix('/apply/{school}')->name('apply.')->group(function () {
    Route::get('/', [ApplicationFormController::class, 'show'])->name('show');
    Route::post('/', [ApplicationFormController::class, 'store'])->middleware('throttle:applications')->name('store');
    Route::get('/thanks', [ApplicationFormController::class, 'thanks'])->name('thanks');
});

// Paystack payment notifications (signature-checked, then re-verified with Paystack).
Route::post('/webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

// Invitation emails: choose a password from a signed, single-use link.
Route::middleware(['signed', 'throttle:6,1'])->group(function () {
    Route::get('/invitation/{user:ulid}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation/{user:ulid}', [InvitationController::class, 'store'])->name('invitation.store');
});

/*
|--------------------------------------------------------------------------
| Signed-in area
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/app', AppEntryController::class)->name('app');
    Route::get('/app/schools', SchoolPickerController::class)->name('schools.pick');
    Route::get('/account', [AccountController::class, 'edit'])->name('account');

    // Everything inside a school. {school} is the school's slug; see EnsureSchoolContext.
    Route::prefix('app/{school}')
        ->middleware(['school', 'school.active'])
        ->name('school.')
        ->group(function () {
            Route::get('/', School\DashboardController::class)->name('dashboard');

            // Parent and student portal
            Route::get('/portal', [PortalController::class, 'index'])->name('portal');
            Route::get('/portal/children/{student}', [PortalController::class, 'child'])->name('portal.child');
            Route::post('/portal/invoices/{invoice}/pay', [Portal\FeeController::class, 'pay'])->name('portal.invoices.pay');
            Route::get('/portal/payments/callback', [Portal\FeeController::class, 'callback'])->name('portal.payments.callback');

            // Classwork: assignments, quizzes and CBT exams
            Route::middleware('can:scores.enter')->prefix('/classwork')->name('classwork.')->group(function () {
                Route::get('/', [School\ClassworkController::class, 'index'])->name('index');
                Route::post('/quizzes', [School\ClassworkController::class, 'storeQuiz'])->name('quizzes.store');
                Route::get('/quizzes/{quiz}', [School\ClassworkController::class, 'editQuiz'])->name('quizzes.edit');
                Route::patch('/quizzes/{quiz}', [School\ClassworkController::class, 'updateQuiz'])->name('quizzes.update');
                Route::put('/quizzes/{quiz}/questions', [School\ClassworkController::class, 'saveQuestions'])->name('quizzes.questions');
                Route::post('/quizzes/{quiz}/publish', [School\ClassworkController::class, 'togglePublish'])->name('quizzes.publish');
                Route::get('/quizzes/{quiz}/results', [School\ClassworkController::class, 'quizResults'])->name('quizzes.results');
                Route::post('/quizzes/{quiz}/scorebook', [School\ClassworkController::class, 'sendToScorebook'])->name('quizzes.scorebook');
                Route::post('/assignments', [School\ClassworkController::class, 'storeAssignment'])->name('assignments.store');
                Route::get('/assignments/{assignment}', [School\ClassworkController::class, 'showAssignment'])->name('assignments.show');
                Route::patch('/submissions/{submission}', [School\ClassworkController::class, 'markSubmission'])->name('submissions.mark');
            });
            Route::get('/portal/classwork', [Portal\ClassworkController::class, 'index'])->name('portal.classwork');
            Route::get('/portal/assignments/{assignment}', [Portal\ClassworkController::class, 'assignment'])->name('portal.assignments.show');
            Route::post('/portal/assignments/{assignment}', [Portal\ClassworkController::class, 'submitAssignment'])->name('portal.assignments.submit');
            Route::get('/portal/quizzes/{quiz}', [Portal\ClassworkController::class, 'quiz'])->name('portal.quizzes.show');
            Route::post('/portal/quizzes/{quiz}/start', [Portal\ClassworkController::class, 'start'])->name('portal.quizzes.start');
            Route::put('/portal/quizzes/{quiz}/answers', [Portal\ClassworkController::class, 'answer'])->name('portal.quizzes.answer');
            Route::post('/portal/quizzes/{quiz}/submit', [Portal\ClassworkController::class, 'submit'])->name('portal.quizzes.submit');

            // Attendance
            Route::middleware('can:attendance.take')->group(function () {
                Route::get('/attendance', [School\AttendanceController::class, 'index'])->name('attendance.index');
                Route::get('/attendance/{classArm}', [School\AttendanceController::class, 'register'])->name('attendance.register');
                Route::put('/attendance/{classArm}', [School\AttendanceController::class, 'save'])->name('attendance.save');
            });

            // Fees
            Route::middleware('can:fees.manage')->group(function () {
                Route::get('/fees/setup', [School\FeeSetupController::class, 'index'])->name('fees.setup');
                Route::post('/fees/items', [School\FeeSetupController::class, 'storeItem'])->name('fees.items.store');
                Route::post('/fees/items/defaults', [School\FeeSetupController::class, 'defaults'])->name('fees.items.defaults');
                Route::patch('/fees/items/{feeItem}', [School\FeeSetupController::class, 'updateItem'])->name('fees.items.update');
                Route::delete('/fees/items/{feeItem}', [School\FeeSetupController::class, 'destroyItem'])->name('fees.items.destroy');
                Route::put('/fees/schedule', [School\FeeSetupController::class, 'saveSchedule'])->name('fees.schedule.update');
                Route::post('/fees/schedule/copy', [School\FeeSetupController::class, 'copySchedule'])->name('fees.schedule.copy');
                Route::post('/invoices/generate', [School\InvoiceController::class, 'generate'])->name('invoices.generate');
                Route::post('/invoices/{invoice}/lines', [School\InvoiceController::class, 'addLine'])->name('invoices.lines.store');
                Route::post('/invoices/{invoice}/void', [School\InvoiceController::class, 'void'])->name('invoices.void');
            });
            Route::middleware('can:finance.view')->group(function () {
                Route::get('/invoices', [School\InvoiceController::class, 'index'])->name('invoices.index');
                Route::get('/invoices/{invoice}', [School\InvoiceController::class, 'show'])->name('invoices.show');
                Route::get('/payments', [School\PaymentController::class, 'index'])->name('payments.index');
                Route::get('/debtors', [School\DebtorController::class, 'index'])->name('debtors.index');
            });
            Route::middleware('can:payments.record')->group(function () {
                Route::post('/students/{student}/payments', [School\PaymentController::class, 'store'])->name('payments.store');
                Route::post('/payments/{payment}/reverse', [School\PaymentController::class, 'reverse'])->name('payments.reverse');
            });
            Route::get('/receipts/{payment}', [School\PaymentController::class, 'receipt'])->name('payments.receipt');

            // Academics
            Route::middleware('can:academics.manage')->group(function () {
                Route::get('/sessions', [School\SessionController::class, 'index'])->name('sessions.index');
                Route::post('/sessions', [School\SessionController::class, 'store'])->name('sessions.store');
                Route::post('/terms/{term}/current', [School\SessionController::class, 'makeCurrent'])->name('terms.current');
                Route::patch('/terms/{term}', [School\SessionController::class, 'update'])->name('terms.update');
                Route::post('/terms/{term}/status', [School\SessionController::class, 'toggleStatus'])->name('terms.status');

                Route::post('/classes', [School\ClassArmController::class, 'store'])->name('classes.store');
                Route::post('/classes/defaults', [School\ClassArmController::class, 'applyDefaults'])->name('classes.defaults');
                Route::patch('/classes/{classArm}', [School\ClassArmController::class, 'update'])->name('classes.update');
                Route::delete('/classes/{classArm}', [School\ClassArmController::class, 'destroy'])->name('classes.destroy');

                Route::get('/subjects', [School\SubjectController::class, 'index'])->name('subjects.index');
                Route::post('/subjects', [School\SubjectController::class, 'store'])->name('subjects.store');
                Route::patch('/subjects/{subject}', [School\SubjectController::class, 'update'])->name('subjects.update');
                Route::delete('/subjects/{subject}', [School\SubjectController::class, 'destroy'])->name('subjects.destroy');
                Route::put('/subjects/{subject}/classes', [School\SubjectController::class, 'syncClasses'])->name('subjects.classes');
                Route::post('/classes/{classArm}/subjects', [School\SubjectController::class, 'assign'])->name('class-subjects.store');
                Route::patch('/class-subjects/{classSubject}', [School\SubjectController::class, 'updateTeacher'])->name('class-subjects.update');
                Route::delete('/class-subjects/{classSubject}', [School\SubjectController::class, 'unassign'])->name('class-subjects.destroy');

                Route::get('/promotion', [School\PromotionController::class, 'index'])->name('promotion.index');
                Route::get('/promotion/{classArm}', [School\PromotionController::class, 'show'])->name('promotion.show');
                Route::post('/promotion/{classArm}', [School\PromotionController::class, 'store'])->name('promotion.store');
            });

            // Results
            Route::middleware('can:scores.enter')->group(function () {
                Route::get('/scores', [School\ScoreController::class, 'index'])->name('scores.index');
                Route::get('/scores/{classSubject}', [School\ScoreController::class, 'edit'])->name('scores.edit');
                Route::put('/scores/{classSubject}', [School\ScoreController::class, 'update'])->name('scores.update');
            });
            Route::middleware('can:results.view')->group(function () {
                Route::get('/results', [School\ResultController::class, 'index'])->name('results.index');
                Route::get('/results/{classArm}', [School\ResultController::class, 'show'])->name('results.show');
                Route::get('/results/{classArm}/remarks', [School\ReportRemarksController::class, 'edit'])->name('remarks.edit');
                Route::put('/results/{classArm}/remarks/{enrolment}', [School\ReportRemarksController::class, 'update'])->name('remarks.update');
                Route::post('/results/{classArm}/remarks/fill', [School\ReportRemarksController::class, 'autofill'])->name('remarks.autofill');
            });
            Route::post('/results/{classArm}/publish', [School\ResultController::class, 'togglePublication'])->middleware('can:results.publish')->name('results.publish');
            Route::get('/report-cards/{student}', [School\ResultController::class, 'report'])->name('results.report');

            Route::middleware('can:students.view')->group(function () {
                Route::get('/classes', [School\ClassArmController::class, 'index'])->name('classes.index');
                Route::get('/classes/{classArm}', [School\ClassArmController::class, 'show'])->name('classes.show');
            });

            // People
            Route::middleware('can:admissions.manage')->prefix('/admissions')->name('admissions.')->group(function () {
                Route::get('/', [School\AdmissionController::class, 'index'])->name('index');
                Route::post('/', [School\AdmissionController::class, 'store'])->name('store');
                Route::post('/bulk', [School\AdmissionController::class, 'bulk'])->name('bulk');
                Route::post('/form', [School\AdmissionController::class, 'toggleForm'])->name('form');
                Route::get('/{applicant}', [School\AdmissionController::class, 'show'])->name('show');
                Route::patch('/{applicant}', [School\AdmissionController::class, 'update'])->name('update');
                Route::post('/{applicant}/enrol', [School\AdmissionController::class, 'enrol'])->name('enrol');
            });
            Route::middleware('can:students.manage')->prefix('/students/import')->name('students.import.')->group(function () {
                Route::get('/', [School\StudentImportController::class, 'create'])->name('create');
                Route::get('/template', [School\StudentImportController::class, 'template'])->name('template');
                Route::post('/', [School\StudentImportController::class, 'store'])->name('store');
                Route::get('/{import}', [School\StudentImportController::class, 'show'])->name('show');
                Route::post('/{import}/start', [School\StudentImportController::class, 'start'])->name('start');
                Route::get('/{import}/status', [School\StudentImportController::class, 'status'])->name('status');
                Route::get('/{import}/logins', [School\StudentImportController::class, 'credentials'])->name('credentials');
                Route::delete('/{import}', [School\StudentImportController::class, 'destroy'])->name('destroy');
            });
            Route::get('/students', [School\StudentController::class, 'index'])->name('students.index');
            Route::get('/students/create', [School\StudentController::class, 'create'])->name('students.create');
            Route::post('/students', [School\StudentController::class, 'store'])->name('students.store');
            Route::get('/students/{student}', [School\StudentController::class, 'show'])->name('students.show');
            Route::get('/students/{student}/edit', [School\StudentController::class, 'edit'])->name('students.edit');
            Route::put('/students/{student}', [School\StudentController::class, 'update'])->name('students.update');
            Route::post('/students/{student}/password', [School\StudentController::class, 'resetPassword'])->name('students.password');

            Route::middleware('can:staff.view')->group(function () {
                Route::get('/staff', [School\StaffController::class, 'index'])->name('staff.index');
                Route::get('/staff/create', [School\StaffController::class, 'create'])->middleware('can:staff.manage')->name('staff.create');
                Route::post('/staff', [School\StaffController::class, 'store'])->middleware('can:staff.manage')->name('staff.store');
                Route::get('/staff/{staff}', [School\StaffController::class, 'show'])->name('staff.show');
            });

            // System
            Route::middleware('can:school.manage')->group(function () {
                Route::get('/school-information', [School\SchoolProfileController::class, 'edit'])->name('profile.edit');
                Route::put('/school-information', [School\SchoolProfileController::class, 'update'])->name('profile.update');
            });

            Route::middleware('can:users.manage')->group(function () {
                Route::get('/users', [School\MemberController::class, 'index'])->name('members.index');
                Route::post('/users/{user:ulid}/toggle', [School\MemberController::class, 'toggle'])->name('members.toggle');
                Route::post('/users/{user:ulid}/invite', [School\MemberController::class, 'invite'])->name('members.invite');
            });

            Route::get('/activity', [School\ActivityController::class, 'index'])->middleware('can:activity.view')->name('activity.index');
        });

    Route::prefix('platform')
        ->middleware('platform.admin')
        ->name('platform.')
        ->group(function () {
            Route::get('/', Platform\DashboardController::class)->name('dashboard');
            Route::get('/schools', [Platform\SchoolController::class, 'index'])->name('schools.index');
            Route::get('/schools/create', [Platform\SchoolController::class, 'create'])->name('schools.create');
            Route::post('/schools', [Platform\SchoolController::class, 'store'])->name('schools.store');
            Route::get('/schools/{school:slug}', [Platform\SchoolController::class, 'show'])->name('schools.show');
            Route::patch('/schools/{school:slug}/status', Platform\SchoolStatusController::class)->name('schools.status');
            Route::get('/leads', [Platform\DemoRequestController::class, 'index'])->name('leads.index');
            Route::patch('/leads/{demoRequest}', [Platform\DemoRequestController::class, 'update'])->name('leads.update');
        });
});
