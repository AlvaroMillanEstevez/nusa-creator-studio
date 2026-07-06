<?php

namespace Tests;

use Database\Seeders\CreatorToolSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $seeder = CreatorToolSeeder::class;
}
