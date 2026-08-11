<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStepStatus;
use App\Enums\WorkflowType;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'requestable_type', 'requestable_id', 'workflow_type', 'requested_by', 'subject_employee_id',
    'status', 'current_step_order', 'submitted_at', 'approved_at', 'rejected_at', 'applied_at',
    'failed_at', 'failure_reason',
])]
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new ApprovalRequest()`) has the same status the DB column default
     * would give a persisted row — the enum cast needs a real value, not null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'current_step_order' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'workflow_type' => WorkflowType::class,
            'current_step_order' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function subjectEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'subject_employee_id');
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    /**
     * The single step currently awaiting a decision, if any.
     *
     * @return HasOne<ApprovalStep, $this>
     */
    public function activeStep(): HasOne
    {
        return $this->hasOne(ApprovalStep::class)->where('status', ApprovalStepStatus::Active);
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }
}
