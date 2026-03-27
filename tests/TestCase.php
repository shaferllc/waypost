<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Host .env may set FLEET_IDP_URL while phpunit.xml tries to clear it; force an empty base URL
        // so Livewire login mount() and policy code do not hit a real IdP unless a test sets config.
        config([
            'fleet_idp.url' => '',
            'fleet_idp.socialite.debug_panel' => false,
        ]);
    }
}
