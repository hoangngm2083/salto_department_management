<?php

namespace App\Models;

use App\Services\Approval\Contracts\ApprovableRequest;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['employee_id', 'project_id', 'start_date', 'end_date', 'reason'])]
class LeaveRequest extends Model implements ApprovableRequest
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
