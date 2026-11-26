<?php

namespace App\Console\Commands;

use App\Actions\Users\RevokeAccess;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateSystemAdmin extends Command
{
    protected $signature = 'app:system-admin {email? : Email address of the new or existing account}';

    protected $description = 'Create a system administrator, or promote an existing account to one';

    public function handle(RevokeAccess $revokeAccess): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?? $this->ask('Email address'))));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $name = null;

        if ($user === null) {
            $name = trim((string) $this->ask('Name'));

            if ($name === '' || Str::length($name) > 255) {
                $this->error('A name of at most 255 characters is required.');

                return self::FAILURE;
            }

            $password = (string) $this->secret('Password');
        } else {
            $this->info("An account for {$email} already exists and will be promoted.");

            $password = (string) $this->secret('New password (leave empty to keep the current one)');
        }

        if ($user === null || $password !== '') {
            $validator = Validator::make(
                ['password' => $password, 'password_confirmation' => (string) $this->secret('Confirm password')],
                ['password' => ['required', 'string', Password::default(), 'confirmed']],
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
        }

        $isNew = $user === null;
        $user ??= new User(['name' => $name, 'email' => $email]);

        if ($password !== '') {
            $user->password = $password;
        }

        $user->forceFill([
            'is_system_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (! $isNew && $password !== '') {
            $revokeAccess->handle($user);
        }

        $this->info($isNew ? "System administrator {$email} created." : "{$email} is now a system administrator.");

        return self::SUCCESS;
    }
}
