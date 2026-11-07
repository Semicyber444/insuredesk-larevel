<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('insuredesk:create-user {--email=} {--name=} {--role=viewer}')]
#[Description('Create a real sign-in account. The password comes from INSUREDESK_USER_PASSWORD or a prompt.')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $name = trim((string) $this->option('name'));
        $role = (string) $this->option('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            $this->error('Usage: php artisan insuredesk:create-user --email you@example.com --name "Name" --role admin');

            return self::FAILURE;
        }
        if (! array_key_exists($role, config('insuredesk.permissions'))) {
            $this->error('Role must be one of: '.implode(', ', array_keys(config('insuredesk.permissions'))));

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists");

            return self::FAILURE;
        }

        // Read from the environment so the password never lands in shell history.
        $password = getenv('INSUREDESK_USER_PASSWORD') ?: $this->secret('Password (at least 12 characters)');
        if (mb_strlen((string) $password) < 12) {
            $this->error('Password must be at least 12 characters');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $role, $password) {
            $user = User::create(['email' => $email, 'name' => $name, 'password' => $password]);
            $user->roles()->create(['role' => $role]);
        });
        $this->info("Created {$role} {$email}");

        return self::SUCCESS;
    }
}
