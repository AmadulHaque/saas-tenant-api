<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\ClientRepository;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Passport token issuance (login/registration) requires a personal access client.
        app(ClientRepository::class)->createPersonalAccessGrantClient('testing', 'users');
    }
}
