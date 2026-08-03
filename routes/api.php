<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
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

    Route::apiResource('employees', EmployeeController::class)
        ->middlewareFor('index', 'abilities:employees:read')
        ->middlewareFor('show', 'abilities:employees:read')
        ->middlewareFor('store', 'abilities:employees:create')
        ->middlewareFor('update', 'abilities:employees:update')
        ->middlewareFor('destroy', 'abilities:employees:delete');

    Route::apiResource('leave-requests', LeaveRequestController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->middlewareFor('index', 'abilities:leave-requests:read')
        ->middlewareFor('show', 'abilities:leave-requests:read')
        ->middlewareFor('store', 'abilities:leave-requests:create')
        ->middlewareFor('update', 'abilities:leave-requests:update');

    Route::post('imports', [ImportController::class, 'store'])->middleware('abilities:imports:create');
    Route::get('imports/{import}', [ImportController::class, 'show'])->middleware('abilities:imports:read');

    Route::get('exports', [ExportController::class, 'download'])->middleware('abilities:exports:read');
});
