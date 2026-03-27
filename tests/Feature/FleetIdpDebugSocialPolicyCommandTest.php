<?php

namespace Tests\Feature;

use Tests\TestCase;

class FleetIdpDebugSocialPolicyCommandTest extends TestCase
{
    public function test_debug_social_policy_command_exits_successfully(): void
    {
        $this->artisan('fleet:idp:debug-social-policy')
            ->assertSuccessful();
    }

    public function test_debug_social_policy_json_option_outputs_json(): void
    {
        $this->artisan('fleet:idp:debug-social-policy', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"resolved_snapshot"');
    }
}
