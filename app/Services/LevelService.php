<?php

namespace App\Services;

use App\Models\Level;
use Illuminate\Contracts\Pagination\CursorPaginator;

class LevelService
{
    /**
     * Get paginated levels with status filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(string $status = 'active', int $perPage = 15): CursorPaginator
    {
        $query = Level::query();

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 'inactive');
        }

        return $query->orderBy('rank')->cursorPaginate($perPage);
    }

    /**
     * Create or update a level record.
     */
    public function upsert(array $data, ?Level $level = null): Level
    {
        if ($level !== null) {
            $level->update($data);

            return $level->fresh();
        }

        return Level::create($data);
    }

    /**
     * Delete a level record.
     */
    public function delete(Level $level): bool
    {
        return (bool) $level->delete();
    }
}
