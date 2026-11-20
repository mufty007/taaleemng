<?php

namespace App\Http\Requests;

use App\Enums\DemoRequestKind;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemoRequestRequest extends FormRequest
{
    public const SOURCES = ['hero', 'cta', 'contact'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Each form on the page reports its errors in its own bag, so the hero and
     * closing call-to-action forms don't both light up.
     */
    protected function prepareForValidation(): void
    {
        $source = $this->input('source');

        $this->errorBag = in_array($source, self::SOURCES, true) ? $source : 'default';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isContactPage = $this->input('source') === 'contact';

        return [
            'source' => ['required', Rule::in(self::SOURCES)],
            'kind' => ['nullable', Rule::enum(DemoRequestKind::class)],
            'contact' => [
                Rule::requiredIf(! $isContactPage),
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

                    if (! $isEmail && User::normalisePhone((string) $value) === null) {
                        $fail('Enter your school email or a Nigerian phone number.');
                    }
                },
            ],
            'name' => [Rule::requiredIf($isContactPage), 'nullable', 'string', 'max:120'],
            'email' => [Rule::requiredIf($isContactPage), 'nullable', 'email', 'max:160'],
            'school_name' => ['nullable', 'string', 'max:160'],
            'student_count' => ['nullable', Rule::in(['Under 50', '50–150', '150–400', '400+'])],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact.required' => 'Enter your school email or phone number.',
        ];
    }
}
