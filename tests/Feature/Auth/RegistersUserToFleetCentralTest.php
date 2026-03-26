<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistersUserToFleetCentralTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_calls_fleet_provisioning_when_configured(): void
    {
        Config::set('fleet_idp.provisioning.token', 'test-provisioning-token');
        Config::set('fleet_idp.url', 'http://fleet-auth.test');

        Http::fake([
            'fleet-auth.test/api/provisioning/users' => Http::response(['status' => 'created'], 201),
        ]);

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Fleet User')
            ->set('email', 'fleet-user@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        Http::assertSent(function ($request) {
            return $request->url() === 'http://fleet-auth.test/api/provisioning/users'
                && $request->hasHeader('Authorization', 'Bearer test-provisioning-token')
                && $request['email'] === 'fleet-user@example.com'
                && $request['name'] === 'Fleet User'
                && $request['password'] === 'password';
        });
    }
}
