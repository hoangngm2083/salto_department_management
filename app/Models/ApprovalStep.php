<?php

namespace App\Models;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverKind;
use Database\Factories\ApprovalStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'approval_request_id', 'step_order', 'approver_kind', 'approver_employee_id',
    'required_permission', 'status', 'acted_by', 'acted_at', 'comment', 'reminder_sent_at',
])]
class ApprovalStep extends Model
{
    /** @use HasFactory<ApprovalStepFactory> */
    use HasFactory;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new ApprovalStep()`) has the same status the DB column default would
     * give a persisted row — the enum cast needs a real value, not null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approver_kind' => ApproverKind::class,
            'status' => ApprovalStepStatus::class,
            'acted_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acted_by');
    }
}
