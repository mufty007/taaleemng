<?php

namespace App\Http\Requests\School;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\TenantRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::studentRules(),
            'class_arm_id' => ['required', TenantRule::exists('class_arms')],
            ...self::guardianRules(),
        ];
    }

    /**
     * Rules for the student's first guardian, shared with the bulk import.
     *
     * @return array<string, array<mixed>>
     */
    public static function guardianRules(): array
    {
        return [
            'guardian_title' => ['nullable', 'string', 'max:20'],
            'guardian_first_name' => ['required', 'string', 'max:60'],
            'guardian_last_name' => ['required', 'string', 'max:60'],
            'guardian_relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'guardian_phone' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (User::normalisePhone((string) $value) === null) {
                    $fail('Enter a valid Nigerian phone number, e.g. 0803 123 4567.');
                }
            }],
            'guardian_email' => ['nullable', 'email', 'max:160'],
            'guardian_occupation' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Rules shared by the add and edit student forms.
     *
     * @return array<string, array<mixed>>
     */
    public static function studentRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:'.now()->subYears(30)->toDateString()],
            'state_of_origin' => ['nullable', Rule::in(config('nigeria.states'))],
            'religion' => ['nullable', Rule::in(['Islam', 'Christianity', 'Other'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'genotype' => ['nullable', Rule::in(['AA', 'AS', 'AC', 'SS', 'SC'])],
            'address' => ['nullable', 'string', 'max:255'],
            'previous_school' => ['nullable', 'string', 'max:120'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'class_arm_id' => 'class',
            'guardian_first_name' => 'parent’s first name',
            'guardian_last_name' => 'parent’s surname',
            'guardian_relationship' => 'relationship',
            'guardian_phone' => 'parent’s phone number',
            'guardian_email' => 'parent’s email',
        ];
    }
}
