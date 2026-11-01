<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ApplicationFoundationSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->seedDemoUsers();
        }
    }

    /**
     * Fictional local accounts so a fresh clone can log in immediately.
     * Both use the factory default password: "password".
     */
    private function seedDemoUsers(): void
    {
        if (! User::query()->where('email', 'demo@example.test')->exists()) {
            User::factory()->create([
                'name' => 'Demo Creator',
                'email' => 'demo@example.test',
                'role' => 'subscriber',
                'plan_key' => 'creator',
                'credits_balance' => 300,
                'monthly_credit_allowance' => 300,
            ]);
        }

        if (! User::query()->where('email', 'admin@example.test')->exists()) {
            User::factory()->admin()->create([
                'name' => 'Demo Admin',
                'email' => 'admin@example.test',
                'plan_key' => 'internal_unlimited',
                'is_unlimited' => true,
                'credits_balance' => 0,
                'monthly_credit_allowance' => 0,
            ]);
        }
    }
}
