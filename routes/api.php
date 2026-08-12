<?php

use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AssignmentRolePeriodController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProjectAssignmentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectManagerController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ProjectRoleController;
use App\Http\Controllers\Api\V1\RoleChangeRequestController;
use App\Http\Controllers\Api\V1\TaskCommentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskDelayRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->middleware('abilities:profile:read');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('abilities:profile:read');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('departments', DepartmentController::class)
        ->scoped(['department' => 'slug'])
        ->middlewareFor('index', 'abilities:departments:read')
        ->middlewareFor('show', 'abilities:departments:read')
        ->middlewareFor('store', 'abilities:departments:create')
        ->middlewareFor('update', 'abilities:departments:update')
        ->middlewareFor('destroy', 'abilities:departments:delete');

    Route::apiResource('levels', LevelController::class)
        ->scoped(['level' => 'slug'])
        ->middlewareFor('index', 'abilities:levels:read')
        ->middlewareFor('show', 'abilities:levels:read')
        ->middlewareFor('store', 'abilities:levels:create')
        ->middlewareFor('update', 'abilities:levels:update')
        ->middlewareFor('destroy', 'abilities:levels:delete');

    Route::get('employees/count', [EmployeeController::class, 'count'])
        ->middleware('abilities:employees:read');

    Route::apiResource('employees', EmployeeController::class)
        ->middlewareFor('index', 'abilities:employees:read')
        ->middlewareFor('show', 'abilities:employees:read')
        ->middlewareFor('store', 'abilities:employees:create')
        ->middlewareFor('update', 'abilities:employees:update')
        ->middlewareFor('destroy', 'abilities:employees:delete');

    Route::get('employees/{employee}/projects', [EmployeeController::class, 'workHistory'])
        ->middleware('abilities:employees:read');
    Route::get('employees/{employee}/tasks', [EmployeeController::class, 'tasks'])
        ->middleware('abilities:tasks:read');
    Route::get('employees/{employee}/managed-projects', [EmployeeController::class, 'managedProjects'])
        ->middleware('abilities:employees:read');
    Route::get('employees/{employee}/overdue-tasks', [EmployeeController::class, 'overdueManagedTasks'])
        ->middleware('abilities:tasks:read');

    Route::apiResource('projects', ProjectController::class)
        ->scoped(['project' => 'slug'])
        ->middlewareFor('index', 'abilities:projects:read')
        ->middlewareFor('show', 'abilities:projects:read')
        ->middlewareFor('store', 'abilities:projects:create')
        ->middlewareFor('update', 'abilities:projects:update')
        ->middlewareFor('destroy', 'abilities:projects:delete');

    Route::post('projects/{project:slug}/managers', [ProjectManagerController::class, 'store'])
        ->middleware('abilities:projects:manage-managers');
    Route::delete('projects/{project:slug}/managers/{projectManager}', [ProjectManagerController::class, 'destroy'])
        ->middleware('abilities:projects:manage-managers');

    Route::get('projects/{project:slug}/assignments', [ProjectAssignmentController::class, 'index'])
        ->middleware('abilities:projects:read');
    Route::post('projects/{project:slug}/assignments', [ProjectAssignmentController::class, 'store'])
        ->middleware('abilities:projects:manage-assignments');
    Route::delete('projects/{project:slug}/assignments/{assignment}', [ProjectAssignmentController::class, 'destroy'])
        ->middleware('abilities:projects:manage-assignments');

    Route::post('projects/{project:slug}/assignments/{assignment}/roles', [AssignmentRolePeriodController::class, 'store'])
        ->middleware('abilities:projects:manage-assignments');
    Route::delete('projects/{project:slug}/assignments/{assignment}/roles/{rolePeriod}', [AssignmentRolePeriodController::class, 'destroy'])
        ->middleware('abilities:projects:manage-assignments');

    Route::get('projects/{project:slug}/members', [ProjectMemberController::class, 'index'])
        ->middleware('abilities:projects:read');

    Route::get('projects/{project:slug}/tasks', [TaskController::class, 'index'])
        ->middleware('abilities:tasks:read');
    Route::post('projects/{project:slug}/tasks', [TaskController::class, 'store'])
        ->middleware('abilities:tasks:create');
    Route::get('tasks/{task}', [TaskController::class, 'show'])
        ->middleware('abilities:tasks:read');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])
        ->middleware('abilities:tasks:update');
    Route::patch('tasks/{task}/assign', [TaskController::class, 'assign'])
        ->middleware('abilities:tasks:update');

    Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index'])
        ->middleware('abilities:task-comments:read');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])
        ->middleware('abilities:task-comments:create');

    Route::apiResource('task-delay-requests', TaskDelayRequestController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->middlewareFor('index', 'abilities:task-delay-requests:read')
        ->middlewareFor('show', 'abilities:task-delay-requests:read')
        ->middlewareFor('store', 'abilities:task-delay-requests:create')
        ->middlewareFor('update', 'abilities:task-delay-requests:update');

    Route::apiResource('project-roles', ProjectRoleController::class)
        ->scoped(['project_role' => 'slug'])
        ->middlewareFor('index', 'abilities:project-roles:read')
        ->middlewareFor('show', 'abilities:project-roles:read')
        ->middlewareFor('store', 'abilities:project-roles:create')
        ->middlewareFor('update', 'abilities:project-roles:update')
        ->middlewareFor('destroy', 'abilities:project-roles:delete');

    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['store', 'show'])
        ->middlewareFor('store', 'abilities:leave-requests:create')
        ->middlewareFor('show', 'abilities:leave-requests:read');

    Route::apiResource('role-change-requests', RoleChangeRequestController::class)
        ->only(['store', 'show'])
        ->middlewareFor('store', 'abilities:role-change-requests:create')
        ->middlewareFor('show', 'abilities:role-change-requests:read');

    Route::apiResource('approvals', ApprovalController::class)
        ->only(['index', 'show', 'update'])
        ->middlewareFor('index', 'abilities:approvals:read')
        ->middlewareFor('show', 'abilities:approvals:read')
        ->middlewareFor('update', 'abilities:approvals:update');

    Route::post('imports', [ImportController::class, 'store'])->middleware('abilities:imports:create');
    Route::get('imports/{import}', [ImportController::class, 'show'])->middleware('abilities:imports:read');

    Route::get('exports', [ExportController::class, 'download'])->middleware('abilities:exports:read');

    Route::get('notifications', [NotificationController::class, 'index'])->middleware('abilities:notifications:read');
    Route::patch('notifications', [NotificationController::class, 'updateMany'])->middleware('abilities:notifications:update');
    Route::patch('notifications/{notification}', [NotificationController::class, 'update'])->middleware('abilities:notifications:update');
});
