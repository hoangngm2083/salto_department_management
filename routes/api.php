<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LevelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProjectRoleController;
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

    Route::apiResource('employees', EmployeeController::class)
        ->middlewareFor('index', 'abilities:employees:read')
        ->middlewareFor('show', 'abilities:employees:read')
        ->middlewareFor('store', 'abilities:employees:create')
        ->middlewareFor('update', 'abilities:employees:update')
        ->middlewareFor('destroy', 'abilities:employees:delete');

    Route::apiResource('project-roles', ProjectRoleController::class)
        ->scoped(['project_role' => 'slug'])
        ->middlewareFor('index', 'abilities:project-roles:read')
        ->middlewareFor('show', 'abilities:project-roles:read')
        ->middlewareFor('store', 'abilities:project-roles:create')
        ->middlewareFor('update', 'abilities:project-roles:update')
        ->middlewareFor('destroy', 'abilities:project-roles:delete');

    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->middlewareFor('index', 'abilities:leave-requests:read')
        ->middlewareFor('show', 'abilities:leave-requests:read')
        ->middlewareFor('store', 'abilities:leave-requests:create')
        ->middlewareFor('update', 'abilities:leave-requests:update');

    Route::post('imports', [ImportController::class, 'store'])->middleware('abilities:imports:create');
    Route::get('imports/{import}', [ImportController::class, 'show'])->middleware('abilities:imports:read');

    Route::get('exports', [ExportController::class, 'download'])->middleware('abilities:exports:read');

    Route::get('notifications', [NotificationController::class, 'index'])->middleware('abilities:notifications:read');
    Route::patch('notifications', [NotificationController::class, 'updateMany'])->middleware('abilities:notifications:update');
    Route::patch('notifications/{notification}', [NotificationController::class, 'update'])->middleware('abilities:notifications:update');
});
