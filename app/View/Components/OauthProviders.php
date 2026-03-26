<?php

namespace App\View\Components;

use Closure;
use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class OauthProviders extends Component
{
    public bool $githubEnabled;

    public bool $googleEnabled;

    public bool $fleetAuthEnabled;

    public bool $anyEnabled;

    public function __construct()
    {
        $this->githubEnabled = self::providerConfigured('github');
        $this->googleEnabled = self::providerConfigured('google');
        $this->fleetAuthEnabled = self::fleetAuthConfigured();
        $this->anyEnabled = $this->githubEnabled || $this->googleEnabled || $this->fleetAuthEnabled;
    }

    public static function isEnabled(): bool
    {
        return self::providerConfigured('github')
            || self::providerConfigured('google')
            || self::fleetAuthConfigured();
    }

    private static function fleetAuthConfigured(): bool
    {
        return FleetIdpOAuth::isConfigured();
    }

    private static function providerConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return is_array($config)
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }

    public function render(): View|Closure|string
    {
        return view('components.oauth-providers');
    }
}
