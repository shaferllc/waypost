<?php

namespace Tests\Feature;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FleetSocialLoginPolicyCachePurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fleet_idp.provisioning.policy_cache_purge_enabled' => true,
            'fleet_idp.provisioning.token' => 'purge-test-token',
            'fleet_idp.url' => 'https://fleet-auth.example.test',
            'fleet_idp.client_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'fleet_idp.client_secret' => 'oauth-secret',
            'fleet_idp.socialite.policy_cache_seconds' => 120,
        ]);

        FleetSocialLoginPolicy::clearFake();
    }

    public function test_purge_endpoint_forgets_policy_cache_with_valid_bearer(): void
    {
        $key = FleetSocialLoginPolicy::policyCacheKey();
        Cache::put($key, ['email_login_code' => true, 'email_login_magic_link' => false], 120);
        $this->assertNotNull(Cache::get($key));

        $this->deleteJson('/api/fleet-idp/social-login/policy-cache', [], [
            'Authorization' => 'Bearer purge-test-token',
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull(Cache::get($key));
    }

    public function test_purge_endpoint_accepts_post(): void
    {
        $key = FleetSocialLoginPolicy::policyCacheKey();
        Cache::put($key, ['email_login_code' => true], 120);

        $this->postJson('/api/fleet-idp/social-login/policy-cache', [], [
            'Authorization' => 'Bearer purge-test-token',
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull(Cache::get($key));
    }

    public function test_purge_endpoint_rejects_missing_or_invalid_bearer(): void
    {
        $key = FleetSocialLoginPolicy::policyCacheKey();
        Cache::put($key, ['email_login_code' => true], 120);

        $this->deleteJson('/api/fleet-idp/social-login/policy-cache')
            ->assertUnauthorized();

        $this->deleteJson('/api/fleet-idp/social-login/policy-cache', [], [
            'Authorization' => 'Bearer wrong',
        ])
            ->assertUnauthorized();

        $this->assertNotNull(Cache::get($key));
    }

    public function test_purge_endpoint_is_not_registered_when_disabled(): void
    {
        config(['fleet_idp.provisioning.policy_cache_purge_enabled' => false]);

        $this->deleteJson('/api/fleet-idp/social-login/policy-cache', [], [
            'Authorization' => 'Bearer purge-test-token',
        ])
            ->assertNotFound();
    }
}
