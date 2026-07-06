<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ApplicationFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CreatorToolSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);
    }
}
