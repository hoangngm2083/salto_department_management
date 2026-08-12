<?php

namespace App\Services\Export\Handlers;

use App\Models\Department;
use App\Services\Export\AbstractExportHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DepartmentExportHandler extends AbstractExportHandler
{
    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return ['name', 'slug', 'description', 'status'];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function query(array $filters): Builder
    {
        $status = $filters['status'] ?? 'active';

        $query = Department::query();

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 'inactive');
        }

        return $query;
    }

    /**
     * @param  Department  $record
     * @return list<string|null>
     */
    protected function toRow(Model $record): array
    {
        return [
            $record->name,
            $record->slug,
            $record->description ?? '',
            $record->status,
        ];
    }
}
