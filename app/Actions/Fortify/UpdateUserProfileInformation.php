<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string|null>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input['phone'] = filled($input['phone'] ?? null) ? (User::normalisePhone($input['phone']) ?? $input['phone']) : null;

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required_without:phone',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => [
                'required_without:email',
                'nullable',
                'regex:/^\+234\d{10}$/',
                Rule::unique('users')->ignore($user->id),
            ],
        ], [
            'phone.regex' => 'Enter a valid Nigerian phone number, e.g. 0803 123 4567.',
        ])->validateWithBag('updateProfileInformation');

        $emailChanged = ($input['email'] ?? null) !== $user->email;

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'] ?? null,
            'phone' => $input['phone'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();
    }
}
