<?php

namespace App\Http\Requests\School;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An application typed in at the school office. The public form uses the same
 * fields (see fields()), checked once the school is in context.
 */
class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admissions.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::fields(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The child and parent details every application needs.
     *
     * @return array<string, array<mixed>>
     */
    public static function fields(): array
    {
        $student = StoreStudentRequest::studentRules();

        return [
            'class_level_id' => ['required', TenantRule::exists('class_levels')],
            'first_name' => $student['first_name'],
            'middle_name' => $student['middle_name'],
            'last_name' => $student['last_name'],
            'gender' => $student['gender'],
            'date_of_birth' => ['required', ...array_diff($student['date_of_birth'], ['nullable'])],
            'state_of_origin' => $student['state_of_origin'],
            'religion' => $student['religion'],
            'previous_school' => $student['previous_school'],
            'address' => $student['address'],
            ...StoreStudentRequest::guardianRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::fieldNames();
    }

    /**
     * @return array<string, string>
     */
    public static function fieldNames(): array
    {
        return [
            'class_level_id' => 'class',
            'guardian_first_name' => 'parent’s first name',
            'guardian_last_name' => 'parent’s surname',
            'guardian_relationship' => 'relationship',
            'guardian_phone' => 'parent’s phone number',
            'guardian_email' => 'parent’s email',
        ];
    }
}
