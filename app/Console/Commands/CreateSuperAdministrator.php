<?php

namespace App\Console\Commands;

use App\Enums\Access\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

#[Signature('lccms:create-super-admin {email} {name}')]
#[Description('Create the first System Super Administrator and email them a password set-up link')]
class CreateSuperAdministrator extends Command
{
    public function handle(): int
    {
        $data = [
            'email' => (string) $this->argument('email'),
            'name' => (string) $this->argument('name'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $user = User::query()->create([
            ...$data,
            'password' => str()->password(32),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(RoleName::SystemSuperAdministrator->value);

        Password::sendResetLink(['email' => $user->email]);

        $this->info("Super administrator {$user->email} created. A password set-up link has been emailed.");
        if (config('lccms.security.strong_authentication')) {
            $this->line('Two-factor authentication or a passkey will be required at first sign-in.');
        }

        return self::SUCCESS;
    }
}
