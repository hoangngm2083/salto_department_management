<?php

namespace App\Models;

use App\Enums\ProjectAssignmentStatus;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'status', 'start_date', 'end_date'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new Project()`) has the same status the DB column default would give
     * a persisted row — the enum cast needs a real value, not null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return HasMany<ProjectManager, $this>
     */
    public function managers(): HasMany
    {
        return $this->hasMany(ProjectManager::class);
    }

    /**
     * @return HasMany<ProjectManager, $this>
     */
    public function activeManagers(): HasMany
    {
        return $this->managers()->whereNull('end_date');
    }

    /**
     * Projects the given employee is currently an active manager of - shared
     * by `ProjectService::getPaginated()`'s `manager_employee_id` filter and
     * `getManagedByEmployee()`, which independently duplicated this same
     * `whereHas('activeManagers', ...)` clause before this was extracted.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeManagedBy(Builder $query, int $employeeId): Builder
    {
        return $query->whereHas('activeManagers', fn ($managers) => $managers->where('employee_id', $employeeId));
    }

    /**
     * @return HasMany<ProjectAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    /**
     * @return HasMany<ProjectAssignment, $this>
     */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('status', ProjectAssignmentStatus::Active);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
