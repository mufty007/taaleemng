<?php

use App\Enums\StockMovementType;
use App\Models\Assignment;
use App\Models\ClassArm;
use App\Models\QuizAttempt;
use App\Models\School;
use App\Models\SpecialExam;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Support\Reports\ReportCatalogue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

it('seeds the demo schools with working logins', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);

    $greenfield = School::query()->where('slug', 'greenfield')->sole();

    expect(School::query()->count())->toBe(3)
        ->and(inSchool($greenfield, fn () => Student::query()->count()))->toBeGreaterThan(250)
        ->and(inSchool($greenfield, fn () => Assignment::query()->count()))->toBeGreaterThan(50)
        ->and(inSchool($greenfield, fn () => QuizAttempt::query()->count()))->toBeGreaterThan(500)
        ->and(inSchool($greenfield, fn () => TimetableEntry::query()->count()))->toBeGreaterThan(500)
        ->and(inSchool($greenfield, fn () => StockMovement::query()->where('type', StockMovementType::Sale)->count()))->toBeGreaterThan(20)
        ->and(inSchool($greenfield, fn () => ClassArm::query()->whereNull('capacity')->count()))->toBe(0)
        ->and($greenfield->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($greenfield->logo_path);

    foreach (['admin@taaleem.test', 'proprietor@greenfield.test', 'teacher@greenfield.test', 'sani@teachers.test'] as $email) {
        $this->post(route('login.store'), ['login' => $email, 'password' => 'password'])->assertRedirect('/app');
        $this->assertAuthenticatedAs(User::query()->where('email', $email)->sole());
        $this->post(route('logout'));
    }

    $aisha = inSchool($greenfield, fn () => Student::query()->where('first_name', 'Aisha')->where('last_name', 'Bello')->sole());

    $this->post(route('login.store'), ['school_code' => 'GFA', 'login' => $aisha->admission_no, 'password' => 'password'])->assertRedirect('/app');
    $this->post(route('logout'));

    $this->post(route('login.store'), ['login' => '0802 123 4567', 'password' => 'password'])->assertRedirect('/app');
    $this->get('/app')->assertRedirect(route('schools.pick'));
    $this->post(route('logout'));

    // Every report runs on a full school's worth of data.
    $proprietor = User::query()->where('email', 'proprietor@greenfield.test')->sole();

    foreach (ReportCatalogue::keys() as $report) {
        $this->actingAs($proprietor)->get(route('school.reports.show', [$greenfield, $report]))->assertOk();
    }

    // And so do the mock and practice exams.
    $exams = inSchool($greenfield, fn () => SpecialExam::query()->get());
    expect($exams)->toHaveCount(4);

    foreach ($exams as $exam) {
        $this->actingAs($proprietor)->get(route('school.special-exams.show', [$greenfield, $exam]))->assertOk();
    }
})->group('slow');
