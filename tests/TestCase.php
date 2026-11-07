<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/** Runs against real MySQL (phpunit.xml: insuredesk_laravel_test), freshly migrated and seeded with the demo data. */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public const INSURER_ID = '20000000-0000-0000-0000-000000000001';

    protected function demoUser(string $role): User
    {
        return User::findOrFail(User::DEMO_USER_IDS[$role]);
    }
}
