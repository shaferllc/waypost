<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_redirect_returns_not_found_when_github_is_not_configured(): void
    {
        config([
            'services.github.client_id' => null,
            'services.github.client_secret' => null,
        ]);

        $this->get(route('oauth.redirect', ['provider' => 'github']))
            ->assertNotFound();
    }

    public function test_oauth_redirect_returns_not_found_when_google_is_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get(route('oauth.redirect', ['provider' => 'google']))
            ->assertNotFound();
    }

    public function test_unknown_oauth_provider_returns_not_found(): void
    {
        $this->get('/oauth/twitter')
            ->assertNotFound();
    }

    public function test_fleet_auth_redirect_returns_not_found_when_not_configured(): void
    {
        config([
            'fleet_idp.url' => '',
            'fleet_idp.client_id' => '',
            'fleet_idp.client_secret' => '',
        ]);

        $this->get(route('oauth.fleet-auth.redirect'))
            ->assertNotFound();
    }
}
