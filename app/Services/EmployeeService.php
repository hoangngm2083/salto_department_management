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
        'admin' => ['name', 'email', 'password', 'department_id', 'birthday', 'position', 'current_level_id', 'manager_employee_id', 'status'],
        'manager' => ['name', 'email', 'password', 'birthday', 'status'],
        'employee' => ['name', 'email', 'password', 'birthday'],
    ];

    /**
     * Fields where an explicit null is a meaningful value (e.g. "no manager"),
     * so they're exempt from the null-stripping that otherwise protects fields
     * like password from being wiped by an omitted-then-defaulted null.
     *
     * @var list<string>
     */
    private const NULLABLE_FIELDS = ['current_level_id', 'manager_employee_id'];

    /**
     * Get paginated employees with filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(array $data): CursorPaginator
    {
        $positions = $data['position'] ?? null;

        return Employee::query()
            ->select(['id', 'department_id', 'current_level_id', 'manager_employee_id', 'name', 'email', 'birthday', 'position', 'status', 'created_at', 'updated_at'])
            ->with(['department:id,name,slug', 'currentLevel:id,name,slug', 'manager:id,name'])
            ->when($data['name'] ?? null, fn ($query, $name) => $query->nameContains($name))
            ->when($data['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($data['department_slug'] ?? null, fn ($query, $slug) => $query->whereRelation('department', 'slug', $slug))
            ->when(
                ! empty($positions),
                fn ($query) => $query->whereIn('position', (array) $positions),
                fn ($query) => $query->where('position', '!=', 'admin')
            )
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Create or update an employee record.
     */
    public function upsert(Employee $actor, array $data, ?Employee $employee = null): Employee
    {
        $data = array_filter(
            $data,
            fn (mixed $value, string $key): bool => $value !== null || in_array($key, self::NULLABLE_FIELDS, true),
            ARRAY_FILTER_USE_BOTH
        );
        $data = $this->filterAllowedFields($actor, $data);

        if ($employee === null && $actor->position === 'manager') {
            $data['department_id'] = $actor->department_id;
            $data['position'] = 'employee';
        }

        if ($employee !== null) {
            $employee->update($data);

            return $employee->fresh(['department:id,name,slug', 'currentLevel:id,name,slug', 'manager:id,name']);
        }

        return Employee::create($data)->load(['department:id,name,slug', 'currentLevel:id,name,slug', 'manager:id,name']);
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
