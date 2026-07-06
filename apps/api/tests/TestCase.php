<?php

namespace Tests;

use Database\Seeders\ApplicationFoundationSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $seeder = ApplicationFoundationSeeder::class;
}
