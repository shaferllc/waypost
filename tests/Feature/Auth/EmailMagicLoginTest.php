<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailMagicLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FleetSocialLoginPolicy::clearFake();

        parent::tearDown();
    }

    public function test_magic_link_without_token_redirects_to_login(): void
    {
        $this->get(route('login.email-magic'))
            ->assertRedirect(route('login'));
    }
}
