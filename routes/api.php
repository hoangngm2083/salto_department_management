<?php

use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('departments', DepartmentController::class)->scoped([
    'department' => 'slug',
]);

Route::apiResource('employees', EmployeeController::class);
