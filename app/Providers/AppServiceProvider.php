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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        config([
            'fleet_idp.account.layout' => 'layouts.guest',
            'fleet_idp.account.views.forgot_password' => 'auth.forgot-password',
            'fleet_idp.account.views.reset_password' => 'auth.reset-password',
        ]);

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
