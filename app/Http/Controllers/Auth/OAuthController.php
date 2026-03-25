<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class OAuthController extends Controller
{
    /** @var list<string> */
    private const Providers = ['github', 'google'];

    public function redirect(string $provider): RedirectResponse|SymfonyRedirect
    {
        $provider = $this->validatedProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $provider = $this->validatedProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->with('oauth_error', __('Sign-in was cancelled or failed. Please try again.'));
        }

        $email = $socialUser->getEmail();
        if (! $email) {
            return redirect()->route('login')
                ->with('oauth_error', __('Your account did not return an email address. Add one at the provider or use email sign-in.'));
        }

        $email = Str::lower($email);

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $socialUser->getId())
            ->first();

        if (! $user) {
            $existing = User::query()->where('email', $email)->first();

            if ($existing) {
                if ($existing->provider !== null && $existing->provider !== $provider) {
                    return redirect()->route('login')
                        ->with('oauth_error', __('This email is already linked to another sign-in method.'));
                }

                $existing->forceFill([
                    'provider' => $provider,
                    'provider_id' => (string) $socialUser->getId(),
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                    'name' => $existing->name ?: ($socialUser->getName() ?: Str::before($email, '@')),
                ])->save();

                $user = $existing;
            } else {
                $name = $socialUser->getName() ?: Str::before($email, '@');
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => null,
                    'provider' => $provider,
                    'provider_id' => (string) $socialUser->getId(),
                    'email_verified_at' => now(),
                ]);
            }
        }

        if ($user->hasTwoFactorEnabled()) {
            request()->session()->put([
                'two_factor.id' => $user->id,
                'two_factor.remember' => true,
            ]);
            request()->session()->regenerateToken();

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function validatedProvider(string $provider): string
    {
        $provider = Str::lower($provider);
        if (! in_array($provider, self::Providers, true)) {
            abort(404);
        }

        return $provider;
    }

    private function providerIsConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return is_array($config)
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }
}
