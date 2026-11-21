<?php

use App\Enums\SchoolRole;
use App\Enums\StudentStatus;
use App\Models\ClassArm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'name' => 'Greenfield Academy']);
    $this->admin = memberOf($this->school);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello', 'blood_group' => 'O+'])->student;
    $this->teacher = staffOf($this->school, SchoolRole::Teacher, ['first_name' => 'Ngozi', 'last_name' => 'Eze', 'designation' => 'Mathematics teacher']);
    $this->jss1a = inSchool($this->school, fn () => ClassArm::query()->whereHas('level', fn ($query) => $query->where('name', 'JSS 1'))->where('name', 'A')->sole());
});

it('prints student ID cards for a class with a QR code and the school’s details', function () {
    $this->actingAs($this->admin)->get(route('school.id-cards.index', $this->school))->assertOk()->assertSee('Aisha');

    $this->actingAs($this->admin)->get(route('school.id-cards.students', [$this->school, 'class' => $this->jss1a->ulid]))
        ->assertOk()
        ->assertSee('Aisha Bello')
        ->assertSee($this->aisha->admission_no)
        ->assertSee('Greenfield Academy')
        ->assertSee('O+')
        ->assertSee('<svg', false)
        ->assertSee('Powered by');
});

it('prints only the ticked students, and staff cards for those who may', function () {
    $other = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Zainab'])->student;

    $this->actingAs($this->admin)->get(route('school.id-cards.students', [$this->school, 'class' => $this->jss1a->ulid, 'only' => [$other->ulid]]))
        ->assertOk()->assertSee('Zainab')->assertDontSee('Aisha Bello');

    $this->actingAs($this->admin)->get(route('school.id-cards.staff', $this->school))->assertOk()->assertSee('Ngozi Eze')->assertSee('Mathematics teacher');
    $this->actingAs($this->teacher->user)->get(route('school.id-cards.staff', $this->school))->assertForbidden();
    $this->actingAs($this->teacher->user)->get(route('school.id-cards.index', $this->school))->assertForbidden();
});

it('lets anyone scan a card to check it is genuine and current, showing only what is on the card', function () {
    $verify = route('id-cards.verify', ['school' => 'greenfield', 'kind' => 'student', 'ulid' => $this->aisha->ulid]);

    $this->get($verify)->assertOk()->assertSee('Valid student ID card')->assertSee('Aisha Bello')->assertSee('JSS 1A')->assertDontSee('O+');

    inSchool($this->school, fn () => $this->aisha->update(['status' => StudentStatus::Withdrawn]));
    $this->get($verify)->assertOk()->assertSee('no longer valid');

    $this->get(route('id-cards.verify', ['school' => 'greenfield', 'kind' => 'staff', 'ulid' => $this->teacher->ulid]))->assertOk()->assertSee('Valid staff ID card');
    $this->get(route('id-cards.verify', ['school' => 'greenfield', 'kind' => 'student', 'ulid' => $this->teacher->ulid]))->assertOk()->assertSee('can’t find this card');

    // A card from one school cannot be checked against another.
    schoolWithRoles(['slug' => 'other']);
    $this->get(route('id-cards.verify', ['school' => 'other', 'kind' => 'student', 'ulid' => $this->aisha->ulid]))->assertOk()->assertSee('can’t find this card');
});

it('keeps passport photos private to people who can see the record', function () {
    Storage::fake('local');

    $this->actingAs($this->admin)->post(route('school.students.photo', [$this->school, $this->aisha]), ['photo' => UploadedFile::fake()->image('aisha.png', 600, 800)])
        ->assertSessionHasNoErrors();
    $path = $this->aisha->fresh()->photo_path;
    Storage::disk('local')->assertExists($path);

    $photo = route('school.photos.student', [$this->school, $this->aisha]);
    $this->actingAs($this->admin)->get($photo)->assertOk();
    $this->actingAs($this->aisha->user)->get($photo)->assertOk();
    $this->actingAs($this->teacher->user)->get($photo)->assertForbidden();

    $this->actingAs($this->admin)->post(route('school.students.photo', [$this->school, $this->aisha]), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');
    $this->actingAs($this->teacher->user)->post(route('school.students.photo', [$this->school, $this->aisha]), ['photo' => UploadedFile::fake()->image('x.png')])
        ->assertForbidden();

    $this->actingAs($this->admin)->post(route('school.students.photo', [$this->school, $this->aisha]), ['remove' => 1])->assertSessionHasNoErrors();
    expect($this->aisha->fresh()->photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('uploads a school logo that appears on printed documents', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->put(route('school.profile.update', $this->school), [
        'name' => 'Greenfield Academy', 'logo' => UploadedFile::fake()->image('crest.png', 256, 256),
    ])->assertSessionHasNoErrors();

    $logo = $this->school->fresh()->logo_path;
    expect($logo)->not->toBeNull();
    Storage::disk('public')->assertExists($logo);

    $this->actingAs($this->admin)->get(route('school.id-cards.students', [$this->school, 'class' => $this->jss1a->ulid]))->assertSee($logo);

    $this->actingAs($this->admin)->put(route('school.profile.update', $this->school), ['name' => 'Greenfield Academy', 'logo' => UploadedFile::fake()->image('tiny.png', 20, 20)])
        ->assertSessionHasErrors('logo');

    $this->actingAs($this->admin)->put(route('school.profile.update', $this->school), ['name' => 'Greenfield Academy', 'remove_logo' => 1])->assertSessionHasNoErrors();
    expect($this->school->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($logo);
});
