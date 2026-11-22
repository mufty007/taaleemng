<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The first login on a fresh server: a platform admin who can create schools.
 * Run again with an existing email to promote that user (and, with --password, reset it).
 */
#[Signature('taaleem:admin {email : Email address to log in with} {--name= : Full name (default: Platform Admin)} {--password= : Password to set (a strong one is generated if left out)}')]
#[Description('Create a Ta’aleem platform admin, or promote an existing user to one')]
class MakePlatformAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = $this->option('password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['nullable', 'string', 'min:12']],
            ['password.min' => 'Use at least 12 characters for a platform admin password.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        $generated = $password === null && $user === null ? Str::password(16, symbols: false) : null;

        if ($user === null) {
            $user = new User(['name' => $this->option('name') ?: 'Platform Admin', 'email' => $email]);
        } elseif ($this->option('name')) {
            $user->name = $this->option('name');
        }

        if ($password !== null || $generated !== null) {
            $user->password = $password ?? $generated;
        }

        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->components->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." platform admin {$email}.");

        if ($generated !== null) {
            $this->components->twoColumnDetail('Password', $generated);
            $this->components->warn('Copy this password now; it is not shown again. Change it after you log in.');
        }

        return self::SUCCESS;
    }
}
