<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\Task;
use App\Observers\TaskObserver;
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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Task::observe(TaskObserver::class);
    }
}
