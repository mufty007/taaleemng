<?php

namespace App\Support\People;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Requests\School\StoreStudentRequest;
use App\Models\ClassArm;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reads a student list saved from Excel as CSV and checks every row the way the
 * "Add student" form would, so problems show up before anything is saved.
 *
 * Headings are forgiving ("Surname", "Date of Birth", "Parent Phone"), and so
 * are values: "M"/"F", "Muslim", "jss1a", dates as 14/03/2014.
 */
final class StudentListReader
{
    public const MAX_ROWS = 2000;

    /**
     * Template columns, in order, with a hint for each.
     */
    public const COLUMNS = [
        'admission_no' => 'Optional. Leave blank and we number the student for you.',
        'first_name' => 'Required.',
        'middle_name' => 'Optional.',
        'last_name' => 'Required. The surname.',
        'gender' => 'Required. Male or Female (M or F is fine).',
        'date_of_birth' => 'Optional. Day/month/year, e.g. 14/03/2014.',
        'class' => 'Required. The class this session, e.g. JSS 1A or Primary 4B.',
        'state_of_origin' => 'Optional. e.g. Kano, Lagos, FCT.',
        'religion' => 'Optional. Islam, Christianity or Other.',
        'address' => 'Optional.',
        'guardian_title' => 'Optional. e.g. Mr, Mrs, Alhaji, Dr.',
        'guardian_first_name' => 'Required.',
        'guardian_last_name' => 'Required.',
        'guardian_relationship' => 'Optional. Father, Mother, Guardian, Uncle, Aunt…',
        'guardian_phone' => 'Required. Brothers and sisters with the same parent phone share one parent login.',
        'guardian_email' => 'Optional.',
    ];

    public const REQUIRED = ['first_name', 'last_name', 'gender', 'class', 'guardian_first_name', 'guardian_last_name', 'guardian_phone'];

    private const ALIASES = [
        'admission_number' => 'admission_no', 'adm_no' => 'admission_no', 'reg_no' => 'admission_no',
        'firstname' => 'first_name', 'other_names' => 'middle_name', 'middlename' => 'middle_name', 'middle' => 'middle_name',
        'surname' => 'last_name', 'lastname' => 'last_name', 'family_name' => 'last_name',
        'sex' => 'gender', 'dob' => 'date_of_birth', 'birth_date' => 'date_of_birth',
        'class_arm' => 'class', 'class_name' => 'class', 'state' => 'state_of_origin',
        'parent_title' => 'guardian_title', 'parent_first_name' => 'guardian_first_name', 'parent_last_name' => 'guardian_last_name',
        'parent_surname' => 'guardian_last_name', 'guardian_surname' => 'guardian_last_name', 'relationship' => 'guardian_relationship',
        'parent_phone' => 'guardian_phone', 'phone' => 'guardian_phone', 'parent_email' => 'guardian_email', 'email' => 'guardian_email',
    ];

    private const STUDENT_FIELDS = ['admission_no', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'state_of_origin', 'religion', 'address'];

    /**
     * @return array{rows: list<array{line: int, student: array<string, mixed>, class_arm_id: int, guardian: array<string, mixed>}>, problems: list<array{line: int, name: string, messages: list<string>}>}
     *
     * @throws ValidationException when the file itself cannot be used
     */
    public function read(string $path): array
    {
        [$header, $records] = $this->parse($path);

        $missing = array_diff(self::REQUIRED, $header);

        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'The file is missing these columns: '.implode(', ', $missing).'. Download the template to see the headings we expect.']);
        }

        if ($records === []) {
            throw ValidationException::withMessages(['file' => 'The file has headings but no students.']);
        }

        if (count($records) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Import at most '.self::MAX_ROWS.' students at a time. Split the list into smaller files.']);
        }

        $classes = $this->classLookup();
        $takenNumbers = Student::query()->pluck('admission_no')->map(fn (string $number) => Str::upper($number))->flip();
        $seenNumbers = [];
        $seenStudents = [];
        $rules = $this->rules();
        $rows = [];
        $problems = [];

        foreach ($records as $line => $values) {
            $data = $this->normalise(array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null)), $classes);
            $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: 'Row '.$line;
            $messages = Validator::make($data, $rules, $this->messages($data), $this->attributes())->errors()->all();

            $number = Str::upper((string) ($data['admission_no'] ?? ''));

            if ($number !== '' && $takenNumbers->has($number)) {
                $messages[] = "Admission number {$data['admission_no']} already belongs to a student in the school.";
            } elseif ($number !== '' && isset($seenNumbers[$number])) {
                $messages[] = "Admission number {$data['admission_no']} is also used on row {$seenNumbers[$number]}.";
            }

            $identity = Str::lower($name.'|'.($data['date_of_birth'] ?? '').'|'.preg_replace('/\D/', '', (string) ($data['guardian_phone'] ?? '')));

            if (isset($seenStudents[$identity])) {
                $messages[] = "This student appears twice: see row {$seenStudents[$identity]}.";
            }

            if ($messages !== []) {
                $problems[] = ['line' => $line, 'name' => $name, 'messages' => array_values(array_unique($messages))];

                continue;
            }

            $seenNumbers[$number] = $line;
            $seenStudents[$identity] = $line;
            $rows[] = [
                'line' => $line,
                'student' => array_filter(array_intersect_key($data, array_flip(self::STUDENT_FIELDS)), fn ($value) => $value !== null),
                'class_arm_id' => (int) $data['class_arm_id'],
                'guardian' => [
                    'title' => $data['guardian_title'] ?? null,
                    'first_name' => $data['guardian_first_name'],
                    'last_name' => $data['guardian_last_name'],
                    'relationship' => $data['guardian_relationship'],
                    'phone' => $data['guardian_phone'],
                    'email' => $data['guardian_email'] ?? null,
                ],
            ];
        }

        return ['rows' => $rows, 'problems' => $problems];
    }

    /**
     * The template as CSV text: headings plus two example rows.
     */
    public static function template(): string
    {
        $lines = [
            array_keys(self::COLUMNS),
            ['', 'Aisha', 'Zainab', 'Bello', 'Female', '14/03/2014', 'JSS 1A', 'Kano', 'Islam', '12 Zoo Road, Kano', 'Hajiya', 'Amina', 'Bello', 'Mother', '0802 123 4567', 'amina.bello@example.com'],
            ['GFA/2023/0042', 'Chinedu', '', 'Okafor', 'M', '02/11/2012', 'JSS 2B', 'Anambra', 'Christianity', '', 'Mr', 'Emeka', 'Okafor', 'Father', '08031234567', ''],
        ];

        return implode("\r\n", array_map(fn (array $line) => implode(',', array_map(fn (string $value) => str_contains($value, ',') ? '"'.$value.'"' : $value, $line)), $lines))."\r\n";
    }

    /**
     * @return array{0: list<string>, 1: array<int, list<string|null>>} headings, and records keyed by spreadsheet row number
     */
    private function parse(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $candidate) => substr_count($firstLine, $candidate))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = null;
        $records = [];
        $line = 0;

        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;

            if ($header === null) {
                $header = array_map(fn (?string $heading) => $this->headingKey((string) $heading), $values);

                continue;
            }

            if (array_filter($values, fn (?string $value) => trim((string) $value) !== '') === []) {
                continue;
            }

            $records[$line] = $values;
        }

        fclose($stream);

        return [$header ?? [], $records];
    }

    private function headingKey(string $heading): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(trim($heading))), '_');

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * @param  array<string, string|null>  $raw
     * @param  array<string, int>  $classes
     * @return array<string, mixed>
     */
    private function normalise(array $raw, array $classes): array
    {
        $data = array_map(fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value, $raw);

        $data['gender'] = match (Str::lower((string) ($data['gender'] ?? ''))) {
            'm', 'male', 'boy' => Gender::Male->value,
            'f', 'female', 'girl' => Gender::Female->value,
            default => $data['gender'] ?? null,
        };

        $data['religion'] = match (Str::lower((string) ($data['religion'] ?? ''))) {
            '' => null,
            'islam', 'muslim' => 'Islam',
            'christianity', 'christian' => 'Christianity',
            default => 'Other',
        };

        if (isset($data['state_of_origin'])) {
            $state = Str::lower($data['state_of_origin']);

            foreach (config()->array('nigeria.states') as $known) {
                if (Str::lower($known) === $state || ($state === 'abuja' && str_contains(Str::lower($known), 'fct'))) {
                    $data['state_of_origin'] = $known;

                    break;
                }
            }
        }

        $relationship = Str::lower((string) ($data['guardian_relationship'] ?? ''));
        $data['guardian_relationship'] = GuardianRelationship::tryFrom($relationship)->value ?? ($relationship === '' || $relationship === 'parent' ? GuardianRelationship::Guardian->value : $relationship);

        $data['date_of_birth'] = $this->date($data['date_of_birth'] ?? null);

        $data['class_arm_id'] = isset($data['class']) ? ($classes[self::classKey($data['class'])] ?? null) : null;

        return $data;
    }

    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return $value;
    }

    /**
     * Every way a class may be written ("JSS 1A", "jss1a", "Pry 4 B") mapped to its id.
     *
     * @return array<string, int>
     */
    private function classLookup(): array
    {
        $lookup = [];

        foreach (ClassArm::query()->with('level')->get() as $arm) {
            $lookup[self::classKey($arm->level->name.$arm->name)] = $arm->id;
            $lookup[self::classKey($arm->level->short_name.$arm->name)] = $arm->id;
        }

        return $lookup;
    }

    private static function classKey(string $name): string
    {
        return Str::upper((string) preg_replace('/[^a-z0-9]/i', '', $name));
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function rules(): array
    {
        $studentRules = StoreStudentRequest::studentRules();
        // One clear message for a mistyped date, not three.
        $studentRules['date_of_birth'] = ['bail', ...$studentRules['date_of_birth']];

        return [
            ...$studentRules,
            ...StoreStudentRequest::guardianRules(),
            'admission_no' => ['nullable', 'string', 'max:30'],
            'class' => ['required'],
            'class_arm_id' => ['required_with:class'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function messages(array $data): array
    {
        return [
            'class_arm_id.required_with' => 'There is no class called “'.($data['class'] ?? '').'”. Write it the way the school names it, e.g. JSS 1A.',
            'gender.enum' => 'Gender must be Male or Female.',
            'date_of_birth.date' => 'Write the date of birth as day/month/year, e.g. 14/03/2014.',
            'state_of_origin.in' => '“'.($data['state_of_origin'] ?? '').'” is not a Nigerian state.',
            'guardian_relationship.enum' => 'Relationship must be one of: '.collect(GuardianRelationship::cases())->map(fn ($case) => $case->value)->join(', ').'.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'first_name' => 'first name', 'middle_name' => 'middle name', 'last_name' => 'surname',
            'date_of_birth' => 'date of birth', 'state_of_origin' => 'state of origin', 'admission_no' => 'admission number',
            'guardian_title' => 'parent’s title', 'guardian_first_name' => 'parent’s first name', 'guardian_last_name' => 'parent’s surname',
            'guardian_relationship' => 'relationship', 'guardian_phone' => 'parent’s phone number', 'guardian_email' => 'parent’s email',
        ];
    }
}
