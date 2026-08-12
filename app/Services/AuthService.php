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
        return $employee->createToken($deviceName, $this->abilitiesFor($employee))->plainTextToken;
    }

    public function revokeCurrentAccessToken(Employee $employee): void
    {
        $employee->currentAccessToken()?->delete();
    }

    /**
     * @return list<string>
     */
    private function abilitiesFor(Employee $employee): array
    {
        $employeeAbilities = [
            'profile:read',
            'employees:read',
            'employees:update',
            'projects:read',
            'tasks:read',
            'tasks:create',
            'tasks:update',
            'task-comments:read',
            'task-comments:create',
            'task-delay-requests:read',
            'task-delay-requests:create',
            'task-delay-requests:update',
            'leave-requests:read',
            'leave-requests:create',
            'role-change-requests:read',
            'role-change-requests:create',
            'project-roles:read',
            'approvals:read',
            'approvals:update',
            'notifications:read',
            'notifications:update',
        ];

        // A manager can do everything an employee can, plus department/staffing
        // management - kept as employeeAbilities + extras so the two lists never
        // drift out of sync when an ability is added/removed for employees.
        $managerAbilities = [
            ...$employeeAbilities,
            'employees:create',
            'departments:read',
            'departments:update',
            'levels:read',
            'projects:manage-assignments',
        ];

        return match ($employee->position) {
            'admin' => ['*'],
            'manager' => $managerAbilities,
            default => $employeeAbilities,
        };
    }
}
