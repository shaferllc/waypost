<?php

namespace Tests\Unit;

use App\Support\WaypostCursorArtifacts;
use Tests\TestCase;

class WaypostPublicBaseUrlTest extends TestCase
{
    public function test_public_base_url_falls_back_to_app_url(): void
    {
        config([
            'app.url' => 'https://waypost.test',
            'waypost.public_url' => '',
        ]);

        $this->assertSame('https://waypost.test', WaypostCursorArtifacts::publicBaseUrl());
    }

    public function test_public_base_url_prefers_waypost_public_url(): void
    {
        config([
            'app.url' => 'http://internal.test',
            'waypost.public_url' => 'https://waypost.test/',
        ]);

        $this->assertSame('https://waypost.test', WaypostCursorArtifacts::publicBaseUrl());
    }
}
