<?php

use App\Enums\SchoolRole;
use App\Enums\TermStatus;
use App\Models\AcademicSession;
use App\Models\Term;

beforeEach(function () {
    $this->school = schoolWithRoles(['slug' => 'greenfield']);
    $this->admin = memberOf($this->school);
});

it('starts a session with three terms on Nigerian dates and makes the first term current', function () {
    $this->actingAs($this->admin)
        ->post(route('school.sessions.store', $this->school), ['start_year' => 2026, 'make_current' => 1])
        ->assertRedirect()
        ->assertSessionHas('status');

    inSchool($this->school, function () {
        $session = AcademicSession::query()->with('terms')->sole();

        expect($session->name)->toBe('2026/2027')
            ->and($session->is_current)->toBeTrue()
            ->and($session->terms->pluck('name')->all())->toBe(['First Term', 'Second Term', 'Third Term'])
            ->and($session->terms[0]->starts_on->toDateString())->toBe('2026-09-14')
            ->and($session->terms[0]->starts_on->isMonday())->toBeTrue()
            ->and($session->terms[1]->starts_on->format('Y-m'))->toBe('2027-01')
            ->and($session->terms[2]->starts_on->format('Y-m'))->toBe('2027-04')
            ->and($session->terms[0]->next_term_begins_on->equalTo($session->terms[1]->starts_on))->toBeTrue()
            ->and(Term::current()->name)->toBe('First Term');
    });
});

it('refuses to create the same session twice', function () {
    $this->actingAs($this->admin)->post(route('school.sessions.store', $this->school), ['start_year' => 2026]);

    $this->actingAs($this->admin)
        ->post(route('school.sessions.store', $this->school), ['start_year' => 2026])
        ->assertSessionHasErrors('start_year');
});

it('keeps exactly one current term when switching', function () {
    $this->actingAs($this->admin)->post(route('school.sessions.store', $this->school), ['start_year' => 2026, 'make_current' => 1]);
    $second = inSchool($this->school, fn () => Term::query()->where('sequence', 2)->sole());

    $this->actingAs($this->admin)->post(route('school.terms.current', [$this->school, $second]))->assertRedirect();

    inSchool($this->school, function () use ($second) {
        expect(Term::query()->where('is_current', true)->pluck('id')->all())->toBe([$second->id])
            ->and(AcademicSession::query()->where('is_current', true)->count())->toBe(1);
    });
});

it('closes and reopens a term', function () {
    $this->actingAs($this->admin)->post(route('school.sessions.store', $this->school), ['start_year' => 2026]);
    $term = inSchool($this->school, fn () => Term::query()->where('sequence', 1)->sole());

    $this->actingAs($this->admin)->post(route('school.terms.status', [$this->school, $term]));
    expect(inSchool($this->school, fn () => $term->fresh()->status))->toBe(TermStatus::Closed);

    $this->actingAs($this->admin)->post(route('school.terms.status', [$this->school, $term]));
    expect(inSchool($this->school, fn () => $term->fresh()->status))->toBe(TermStatus::Open);
});

it('validates term dates', function () {
    $this->actingAs($this->admin)->post(route('school.sessions.store', $this->school), ['start_year' => 2026]);
    $term = inSchool($this->school, fn () => Term::query()->where('sequence', 1)->sole());

    $this->actingAs($this->admin)
        ->patch(route('school.terms.update', [$this->school, $term]), ['starts_on' => '2026-09-14', 'ends_on' => '2026-09-01'])
        ->assertSessionHasErrorsIn('term'.$term->id, 'ends_on');

    $this->actingAs($this->admin)
        ->patch(route('school.terms.update', [$this->school, $term]), ['starts_on' => '2026-09-07', 'ends_on' => '2026-12-11', 'next_term_begins_on' => '2027-01-04'])
        ->assertSessionHasNoErrors();

    expect(inSchool($this->school, fn () => $term->fresh()->starts_on->toDateString()))->toBe('2026-09-07');
});

it('keeps teachers out of the calendar settings', function () {
    $teacher = memberOf($this->school, SchoolRole::Teacher);

    $this->actingAs($teacher)->get(route('school.sessions.index', $this->school))->assertForbidden();
    $this->actingAs($teacher)->post(route('school.sessions.store', $this->school), ['start_year' => 2026])->assertForbidden();
});
