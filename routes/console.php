<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:assign-role {email} {role}', function (string $email, string $role): int {
    $validRoles = Role::query()->pluck('slug')->all();

    if (! in_array($role, $validRoles, true)) {
        $this->error('Choose one of these roles: '.implode(', ', $validRoles).'.');

        return Command::FAILURE;
    }

    $user = User::query()->where('email', Str::lower(trim($email)))->first();

    if ($user === null) {
        $this->error('No account was found with that email. Register the account first.');

        return Command::FAILURE;
    }

    $user->role = $role;
    $user->save();

    $this->info("Updated {$user->email} to the {$role} role.");

    return Command::SUCCESS;
})->purpose('Assign an approved role to an existing account');
