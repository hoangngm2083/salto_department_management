<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Pagination\CursorPaginator;

class EmployeeService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_UPDATE_FIELDS_BY_ROLE = [
        'admin' => ['name', 'email', 'password', 'department_id', 'birthday', 'position'],
        'manager' => ['name', 'email', 'password', 'birthday'],
        'employee' => ['name', 'email', 'password', 'birthday'],
    ];

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
    public function upsert(Employee $actor, array $data, ?Employee $employee = null): Employee
    {
        $data = array_filter($data, fn (mixed $value): bool => $value !== null);
        $data = $this->filterAllowedFields($actor, $data);

        if ($employee === null && $actor->position === 'manager') {
            $data['department_id'] = $actor->department_id;
            $data['position'] = 'employee';
        }

        if ($employee !== null) {
            $employee->update($data);

            return $employee->fresh(['department:id,name']);
        }

        return Employee::create($data)->load('department:id,name');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterAllowedFields(Employee $actor, array $data): array
    {
        $allowedFields = self::ALLOWED_UPDATE_FIELDS_BY_ROLE[$actor->position] ?? [];

        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Delete an employee record.
     */
    public function delete(Employee $employee): bool
    {
        return (bool) $employee->delete();
    }
}
