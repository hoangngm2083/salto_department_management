<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Contracts\Pagination\CursorPaginator;

class DepartmentService
{
    /**
     * Get paginated departments with status filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(string $status = 'active', int $perPage = 15): CursorPaginator
    {
        $query = Department::query();

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 'inactive');
        }

        return $query->orderBy('id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * Create or update a department record.
     */
    public function upsert(array $data, ?Department $department = null): Department
    {
        if ($department !== null) {
            $department->update($data);

            return $department->fresh();
        }

        return Department::create($data);
    }

    /**
     * Delete a department record.
     */
    public function delete(Department $department): bool
    {
        return (bool) $department->delete();
    }
}
