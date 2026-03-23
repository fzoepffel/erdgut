<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserMakeAdmin extends Command
{
    protected $signature = 'user:make-admin
                            {email : The user\'s email address}';

    protected $description = 'Grant admin access (is_admin) to a user by email. Idempotent if already admin.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $email = trim($email);

        if ($email === '') {
            $this->error('Email must not be empty.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(sprintf('No user found with email [%s].', $email));

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info(sprintf('User [%s] is already an admin.', $email));

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info(sprintf('Admin access granted to [%s].', $email));

        return self::SUCCESS;
    }
}
