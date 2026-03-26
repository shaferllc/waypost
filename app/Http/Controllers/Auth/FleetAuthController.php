<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Fleet\IdpClient\FleetIdpEloquentUserProvisioner;
use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class FleetAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse|SymfonyRedirect
    {
        if (! FleetIdpOAuth::isConfigured()) {
            abort(404);
        }

        return redirect()->away(FleetIdpOAuth::authorizationRedirectUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! FleetIdpOAuth::isConfigured()) {
            abort(404);
        }

        if ($request->query('error')) {
            return redirect()->route('login')
                ->with('oauth_error', (string) $request->query('error_description', __('Sign-in was cancelled or failed.')));
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $stateKey = (string) config('fleet_idp.session_oauth_state_key');
        $expected = $request->session()->pull($stateKey);
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('login')
                ->with('oauth_error', __('Invalid sign-in session. Please try again.'));
        }

        try {
            $tokens = FleetIdpOAuth::exchangeCode((string) $request->query('code'));
            $remote = FleetIdpOAuth::fetchUser($tokens['access_token']);
        } catch (Throwable) {
            return redirect()->route('login')
                ->with('oauth_error', __('Could not complete sign-in. Please try again.'));
        }

        $sync = FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote);
        if ($sync['error'] !== null || ! $sync['user'] instanceof User) {
            return redirect()->route('login')
                ->with('oauth_error', $sync['error'] ?? __('Could not complete sign-in.'));
        }

        $user = $sync['user'];

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                'two_factor.id' => $user->id,
                'two_factor.remember' => true,
            ]);
            $request->session()->regenerateToken();

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
