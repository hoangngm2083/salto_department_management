<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Pagination\CursorPaginator;

class EmployeeService
{
    /**
     * Get paginated employees with filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(array $data): CursorPaginator
    {
        $positions = $data['position'] ?? null;

        return Employee::query()
            ->select(['id', 'department_id', 'name', 'email', 'birthday', 'position', 'created_at', 'updated_at'])
            ->with('department:id,name')
            ->when($data['name'] ?? null, fn ($query, $name) => $query->where('name', 'like', "{$name}%"))
            ->when(
                ! empty($positions),
                fn ($query) => $query->whereIn('position', (array) $positions),
                fn ($query) => $query->where('position', '!=', 'admin')
            )
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? 15);
    }

    /**
     * Create or update an employee record.
     */
    public function upsert(array $data, ?Employee $employee = null): Employee
    {
        if ($employee !== null) {
            $employee->update($data);

            return $employee->fresh(['department:id,name']);
        }

        return Employee::create($data)->load('department:id,name');
    }

    /**
     * Delete an employee record.
     */
    public function delete(Employee $employee): bool
    {
        return (bool) $employee->delete();
    }
}
