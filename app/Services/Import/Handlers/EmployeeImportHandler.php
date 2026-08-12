<?php

namespace App\Services\Import\Handlers;

use App\Models\Department;
use App\Models\Employee;
use App\Services\Import\AbstractImportHandler;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeImportHandler extends AbstractImportHandler
{
    private ?Collection $departmentIdsBySlug = null;

    private ?Collection $existingByEmail = null;

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return ['name', 'email', 'birthday', 'position', 'department_slug'];
    }

    protected function businessKey(): string
    {
        return 'email';
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    protected function rowRules(array $row): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'birthday' => ['required', 'date'],
            'position' => ['nullable', Rule::in(['employee', 'manager', 'admin'])],
            'department_slug' => ['required', 'string', $this->departmentSlugExists()],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function attributesFor(array $row): array
    {
        $existing = $this->existingByEmail?->get($row['email']);

        return [
            'name' => $row['name'],
            'department_id' => (int) $this->departmentIdsBySlug()->get($row['department_slug']),
            'birthday' => $row['birthday'],
            'position' => $row['position'] ?? 'employee',
            'password' => $existing->password ?? Hash::make(Str::random(32)),
            'deleted_at' => null, // reimporting an email always un-trashes it
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<string, Employee>
     */
    protected function findExistingByKeys(array $keys): Collection
    {
        return $this->existingByEmail = Employee::withTrashed()->whereIn('email', $keys)->get()->keyBy('email');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function bulkUpsert(array $rows): void
    {
        Employee::query()->upsert(
            $rows,
            ['email'],
            ['name', 'department_id', 'birthday', 'position', 'password', 'deleted_at']
        );
    }

    /**
     * One departments query per chunk (lazily loaded on first use), never
     * one per row, to check department_slug membership during validation.
     */
    private function departmentSlugExists(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->departmentIdsBySlug()->has($value)) {
                $fail('The selected department_slug is invalid.');
            }
        };
    }

    /**
     * @return Collection<string, int>
     */
    private function departmentIdsBySlug(): Collection
    {
        return $this->departmentIdsBySlug ??= Department::query()->pluck('id', 'slug');
    }
}
