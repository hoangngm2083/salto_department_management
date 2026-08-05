<?php

namespace App\Services\Export\Handlers;

use App\Models\Employee;
use App\Services\Export\AbstractExportHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeExportHandler extends AbstractExportHandler
{
    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return ['name', 'email', 'birthday', 'position', 'department_slug'];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function query(array $filters): Builder
    {
        $positions = $filters['position'] ?? null;

        return Employee::query()
            ->select(['id', 'department_id', 'name', 'email', 'birthday', 'position'])
            ->with('department:id,slug')
            ->when($filters['name'] ?? null, fn ($query, $name) => $query->nameContains($name))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['department_slug'] ?? null, fn ($query, $slug) => $query->whereRelation('department', 'slug', $slug))
            ->when(
                ! empty($positions),
                fn ($query) => $query->whereIn('position', (array) $positions),
                fn ($query) => $query->where('position', '!=', 'admin')
            );
    }

    /**
     * @param  Employee  $record
     * @return list<string|null>
     */
    protected function toRow(Model $record): array
    {
        return [
            $record->name,
            $record->email,
            $record->birthday?->format('Y-m-d') ?? '',
            $record->position,
            $record->department?->slug ?? '',
        ];
    }
}
