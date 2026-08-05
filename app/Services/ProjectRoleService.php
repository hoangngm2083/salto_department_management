<?php

namespace App\Services;

use App\Models\ProjectRole;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ProjectRoleService
{
    /**
     * Get paginated project roles with status filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(string $status = 'active', int $perPage = 15): CursorPaginator
    {
        $query = ProjectRole::query();

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 'inactive');
        }

        return $query->orderBy('id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * Create or update a project role record.
     */
    public function upsert(array $data, ?ProjectRole $projectRole = null): ProjectRole
    {
        if ($projectRole !== null) {
            $projectRole->update($data);

            return $projectRole->fresh();
        }

        return ProjectRole::create($data);
    }

    /**
     * Delete a project role record.
     */
    public function delete(ProjectRole $projectRole): bool
    {
        return (bool) $projectRole->delete();
    }
}
