<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Employee\EmployeeResource;
use App\Models\Employee;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $employee = $this->authService->authenticate($credentials);
        $token = $this->authService->createAccessToken($employee, $credentials['device_name']);

        return $this->successResponse([
            'employee' => new EmployeeResource($employee->loadMissing('department:id,name,slug')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Authenticated successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return $this->successResponse(
            new EmployeeResource($employee->loadMissing('department:id,name,slug')),
            'Authenticated employee retrieved successfully.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $this->authService->revokeCurrentAccessToken($employee);

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
