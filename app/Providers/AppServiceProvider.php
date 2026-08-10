<?php

namespace App\Providers;

use App\Services\ProjectAssignmentCloser;
use App\Services\ProjectAssignmentCloserService;
use App\Services\ProjectManagerGuard;
use App\Services\ProjectManagerGuardService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProjectManagerGuard::class, ProjectManagerGuardService::class);
        $this->app->bind(ProjectAssignmentCloser::class, ProjectAssignmentCloserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                $request->string('email')->lower()->toString().'|'.$request->ip()
            );
        });
    }
}
