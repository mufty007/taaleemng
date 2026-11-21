<?php

use App\Models\Assignment;
use App\Models\QuizAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
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
})->group('slow');
