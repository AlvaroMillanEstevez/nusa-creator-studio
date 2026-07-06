<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserAccess extends Command
{
    protected $signature = 'nusa:user-access
        {email : Email address of an existing user}
        {--role= : One of the supported user roles}
        {--plan= : One of the supported plan keys}
        {--unlimited : Enable unlimited generation access}';

    protected $description = 'Update Nusa role and plan access for a local development user';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This development helper is only available in local and testing environments.');

            return self::FAILURE;
        }

        $role = $this->option('role');
        $plan = $this->option('plan');

        if ($role !== null && ! in_array($role, User::ROLES, true)) {
            $this->error('Invalid role. Allowed: '.implode(', ', User::ROLES));

            return self::INVALID;
        }

        if ($plan !== null && ! in_array($plan, User::PLAN_KEYS, true)) {
            $this->error('Invalid plan. Allowed: '.implode(', ', User::PLAN_KEYS));

            return self::INVALID;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user was found with that email address.');

            return self::FAILURE;
        }

        $updates = array_filter([
            'role' => $role,
            'plan_key' => $plan,
        ], fn ($value) => $value !== null);

        if ($this->option('unlimited')) {
            $updates['is_unlimited'] = true;
        }

        if ($updates === []) {
            $this->warn('No access changes were requested.');

            return self::SUCCESS;
        }

        $user->fill($updates)->save();

        $this->info("Access updated for {$user->email}.");

        return self::SUCCESS;
    }
}
