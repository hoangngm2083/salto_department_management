<?php

namespace App\Services\Import\Handlers;

use App\Models\Department;
use App\Services\Import\AbstractImportHandler;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class DepartmentImportHandler extends AbstractImportHandler
{
    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return ['name', 'slug', 'description', 'status'];
    }

    protected function businessKey(): string
    {
        return 'slug';
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    protected function rowRules(array $row): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function attributesFor(array $row): array
    {
        return [
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'status' => $row['status'] ?? 'active',
            'deleted_at' => null, // reimporting a slug always un-trashes it
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<string, Department>
     */
    protected function findExistingByKeys(array $keys): Collection
    {
        return Department::withTrashed()->whereIn('slug', $keys)->get()->keyBy('slug');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function bulkUpsert(array $rows): void
    {
        Department::query()->upsert(
            $rows,
            ['slug'],
            ['name', 'description', 'status', 'deleted_at']
        );
    }
}
