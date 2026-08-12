<?php

namespace App\Models;

use App\Enums\ProjectAssignmentStatus;
use Database\Factories\ProjectAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'employee_id', 'start_date', 'end_date', 'status', 'assigned_by'])]
class ProjectAssignment extends Model
{
    /** @use HasFactory<ProjectAssignmentFactory> */
    use HasFactory;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new ProjectAssignment()`) has the same status the DB column default
     * would give a persisted row — the enum cast needs a real value, not null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectAssignmentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    /**
     * @return HasMany<AssignmentRolePeriod, $this>
     */
    public function rolePeriods(): HasMany
    {
        return $this->hasMany(AssignmentRolePeriod::class);
    }

    /**
     * @return HasMany<AssignmentRolePeriod, $this>
     */
    public function activeRolePeriods(): HasMany
    {
        return $this->rolePeriods()->whereNull('end_date');
    }
}
