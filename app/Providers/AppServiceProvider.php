<?php

namespace App\Providers;

use App\Services\Approval\ApprovalWorkflowRegistry;
use App\Services\Approval\ApprovedRequestHandlerRegistry;
use App\Services\Approval\Handlers\ApplyRoleChangeHandler;
use App\Services\Approval\Workflows\RoleChangeApprovalWorkflow;
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

        // Each concrete workflow phase tags its own ApprovalWorkflow/ApprovedRequestHandler
        // implementation into these bindings rather than editing this constructor call -
        // empty in Phase D since no concrete workflow exists yet.
        $this->app->tag([RoleChangeApprovalWorkflow::class], 'approval.workflows');
        $this->app->tag([ApplyRoleChangeHandler::class], 'approval.handlers');

        $this->app->bind(ApprovalWorkflowRegistry::class, fn ($app) => new ApprovalWorkflowRegistry($app->tagged('approval.workflows')));
        $this->app->bind(ApprovedRequestHandlerRegistry::class, fn ($app) => new ApprovedRequestHandlerRegistry($app->tagged('approval.handlers')));
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
