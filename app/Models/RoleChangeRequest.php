<?php

namespace App\Models;

use App\Enums\RoleChangeMode;
use App\Services\Approval\Contracts\ApprovableRequest;
use Database\Factories\RoleChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['project_assignment_id', 'change_mode', 'from_project_role_id', 'to_project_role_id', 'reason', 'created_by'])]
class RoleChangeRequest extends Model implements ApprovableRequest
{
    /** @use HasFactory<RoleChangeRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_mode' => RoleChangeMode::class,
        ];
    }

    public function projectAssignment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssignment::class);
    }

    public function fromRole(): BelongsTo
    {
        return $this->belongsTo(ProjectRole::class, 'from_project_role_id');
    }

    public function toRole(): BelongsTo
    {
        return $this->belongsTo(ProjectRole::class, 'to_project_role_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Reverse of `ApprovalRequest::requestable()` - status/steps for this request always
     * live on the linked ApprovalRequest, never duplicated here (see migration comment).
     *
     * @return MorphOne<ApprovalRequest, $this>
     */
    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'requestable');
    }
}
