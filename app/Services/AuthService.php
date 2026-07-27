<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @param  array{email: string, password: string, device_name: string}  $credentials
     */
    public function authenticate(array $credentials): Employee
    {
        $employee = Employee::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($employee === null || ! Hash::check($credentials['password'], $employee->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $employee;
    }

    public function createAccessToken(Employee $employee, string $deviceName): string
    {
        return $employee->createToken($deviceName, ['*'])->plainTextToken;
    }

    public function revokeCurrentAccessToken(Employee $employee): void
    {
        $employee->currentAccessToken()?->delete();
    }
}
