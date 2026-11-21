<?php

use App\Enums\ImportStatus;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentImport;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'short_code' => 'GFA']);
    $this->admin = memberOf($this->school);

    $this->upload = fn (string $csv, string $name = 'jss1.csv') => $this->actingAs($this->admin)->post(
        route('school.students.import.store', $this->school),
        ['file' => UploadedFile::fake()->createWithContent($name, $csv)],
    );
    $this->latestImport = fn () => inSchool($this->school, fn () => StudentImport::query()->latest('id')->firstOrFail());
});

it('offers a template with the expected headings', function () {
    $response = $this->actingAs($this->admin)->get(route('school.students.import.template', $this->school));

    $response->assertOk()->assertDownload('taaleem-student-list.csv');
    expect($response->streamedContent())->toContain('first_name,middle_name,last_name,gender,date_of_birth,class');
});

it('checks a list, then enrols it on the queue and hands out the logins once', function () {
    $csv = "Admission No,First Name,Surname,Sex,Date of Birth,Class,Religion,Parent First Name,Parent Surname,Relationship,Parent Phone\n"
        ."GFA/2019/0007,Aisha,Bello,F,14/03/2014,jss 1a,Muslim,Amina,Bello,Mother,0802 123 4567\n"
        .",Umar,Bello,M,02/01/2013,JSS 2A,Islam,Amina,Bello,mother,08021234567\n"
        .",Chidi,Okafor,Male,,Primary 4A,Christian,Emeka,Okafor,,0803 999 8888\n";

    ($this->upload)($csv)->assertRedirect();
    $import = ($this->latestImport)();

    expect($import->status)->toBe(ImportStatus::Checked)
        ->and($import->total_rows)->toBe(3)
        ->and($import->problems)->toBe([]);

    $this->actingAs($this->admin)->get(route('school.students.import.show', [$this->school, $import]))
        ->assertOk()
        ->assertSee('Import 3 students')
        ->assertSee('BELLO Aisha')
        ->assertSee('Pry 4A');

    $this->actingAs($this->admin)->post(route('school.students.import.start', [$this->school, $import]))->assertRedirect();

    inSchool($this->school, function () {
        $aisha = Student::query()->where('first_name', 'Aisha')->sole();
        $umar = Student::query()->where('first_name', 'Umar')->sole();

        expect(Student::query()->count())->toBe(3)
            ->and($aisha->admission_no)->toBe('GFA/2019/0007')
            ->and($aisha->date_of_birth->toDateString())->toBe('2014-03-14')
            ->and($aisha->religion)->toBe('Islam')
            ->and(Enrolment::query()->where('student_id', $umar->id)->sole()->classArm->displayName())->toBe('JSS 2A')
            ->and(Guardian::query()->count())->toBe(2)
            ->and($aisha->guardians()->sole()->id)->toBe($umar->guardians()->sole()->id);
    });

    $import = ($this->latestImport)();
    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->created_count)->toBe(3)
        ->and($import->rows)->toBe([]);

    $download = $this->actingAs($this->admin)->get(route('school.students.import.credentials', [$this->school, $import]));
    $download->assertOk();
    expect($download->streamedContent())->toContain('GFA + GFA/2019/0007')->toContain('+2348021234567');

    $this->actingAs($this->admin)->get(route('school.students.import.credentials', [$this->school, $import]))
        ->assertSessionHas('status', 'The logins for this import have already been downloaded.');
    expect(($this->latestImport)()->credentials)->toBeNull();
});

it('lists every problem row and keeps the good ones', function () {
    inSchool($this->school, fn () => Student::query()->create(['admission_no' => 'GFA/2020/0001', 'first_name' => 'Old', 'last_name' => 'Pupil', 'gender' => 'male']));

    $csv = "first_name,last_name,gender,date_of_birth,class,admission_no,guardian_first_name,guardian_last_name,guardian_phone\n"
        ."Good,Pupil,F,,JSS 1A,,Ada,Pupil,08030000001\n"
        ."Lost,Pupil,F,,JSS 9Z,,Ada,Pupil,08030000002\n"
        ."Odd,Pupil,X,31/02/2014,JSS 1A,,Ada,Pupil,12345\n"
        ."Taken,Number,M,,JSS 1A,GFA/2020/0001,Ada,Pupil,08030000003\n"
        ."Good,Pupil,F,,JSS 1A,,Ada,Pupil,08030000001\n";

    ($this->upload)($csv);
    $import = ($this->latestImport)();
    $problems = collect($import->problems)->keyBy('line');

    expect($import->total_rows)->toBe(1)
        ->and($problems->keys()->all())->toBe([3, 4, 5, 6])
        ->and($problems[3]['messages'])->toContain('There is no class called “JSS 9Z”. Write it the way the school names it, e.g. JSS 1A.')
        ->and($problems[4]['messages'])->toContain('Gender must be Male or Female.', 'Write the date of birth as day/month/year, e.g. 14/03/2014.', 'Enter a valid Nigerian phone number, e.g. 0803 123 4567.')
        ->and($problems[5]['messages'])->toContain('Admission number GFA/2020/0001 already belongs to a student in the school.')
        ->and($problems[6]['messages'])->toContain('This student appears twice: see row 2.');

    $this->actingAs($this->admin)->get(route('school.students.import.show', [$this->school, $import]))
        ->assertOk()
        ->assertSee('Import 1 student')
        ->assertSee('Row 4');
});

it('rejects a file without the required columns', function () {
    ($this->upload)("name,class\nAisha Bello,JSS 1A\n")
        ->assertSessionHasErrors(['file' => 'The file is missing these columns: first_name, last_name, gender, guardian_first_name, guardian_last_name, guardian_phone. Download the template to see the headings we expect.']);

    inSchool($this->school, fn () => expect(StudentImport::query()->count())->toBe(0));
});

it('reads semicolon-separated files saved by Excel in some locales', function () {
    ($this->upload)("\xEF\xBB\xBFfirst_name;last_name;gender;class;guardian_first_name;guardian_last_name;guardian_phone\nAisha;Bello;F;JSS 1A;Amina;Bello;08021234567\n");

    expect(($this->latestImport)()->total_rows)->toBe(1);
});

it('only lets someone who manages students import', function () {
    $teacher = staffOf($this->school);

    $this->actingAs($teacher->user)->get(route('school.students.import.create', $this->school))->assertForbidden();
    $this->actingAs($teacher->user)->post(route('school.students.import.store', $this->school), ['file' => UploadedFile::fake()->createWithContent('a.csv', 'x')])->assertForbidden();
});
