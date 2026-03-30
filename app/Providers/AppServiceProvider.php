<?php

namespace App\Providers;

use App\Models\OkrGoal;
use App\Models\OkrKeyResult;
use App\Models\OkrObjective;
use App\Models\PersonalAccessToken;
use App\Models\ProjectLink;
use App\Models\Task;
use App\Models\WishlistItem;
use App\Observers\OkrGoalObserver;
use App\Observers\OkrKeyResultObserver;
use App\Observers\OkrObjectiveObserver;
use App\Observers\ProjectLinkObserver;
use App\Observers\TaskObserver;
use App\Observers\WishlistItemObserver;
use Closure;
use Fleet\IdpClient\Http\Middleware\InjectFleetIdpDebugPanel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        config([
            'fleet_idp.account.layout' => 'layouts.guest',
            'fleet_idp.account.views.forgot_password' => 'auth.forgot-password',
            'fleet_idp.account.views.reset_password' => 'auth.reset-password',
            'fleet_idp.email_sign_in.user_code_enabled_attribute' => 'email_login_code_enabled',
            'fleet_idp.email_sign_in.user_magic_link_enabled_attribute' => 'email_login_magic_link_enabled',
            'fleet_idp.email_sign_in.profile_confirm.interstitial_layout' => 'layouts.guest',
            'fleet_idp.email_sign_in.mutually_exclusive_code_and_magic' => true,
        ]);

        if (! config('waypost.fleet_login_enabled')) {
            config([
                'fleet_idp.socialite.debug_panel' => false,
                'fleet_idp.socialite.debug_policy_fetch' => false,
            ]);

            $this->app->bind(InjectFleetIdpDebugPanel::class, function () {
                return new class
                {
                    public function handle(Request $request, Closure $next): Response
                    {
                        return $next($request);
                    }
                };
            });
        }

        if (! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Task::observe(TaskObserver::class);
        WishlistItem::observe(WishlistItemObserver::class);
        ProjectLink::observe(ProjectLinkObserver::class);
        OkrGoal::observe(OkrGoalObserver::class);
        OkrObjective::observe(OkrObjectiveObserver::class);
        OkrKeyResult::observe(OkrKeyResultObserver::class);
    }
}
