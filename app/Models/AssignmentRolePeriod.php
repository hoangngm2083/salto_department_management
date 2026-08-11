<?php

namespace App\Models;

use Database\Factories\AssignmentRolePeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_assignment_id', 'project_role_id', 'start_date', 'end_date', 'source_approval_request_id'])]
class AssignmentRolePeriod extends Model
{
    /** @use HasFactory<AssignmentRolePeriodFactory> */
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

    public function projectAssignment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssignment::class);
    }

    public function projectRole(): BelongsTo
    {
        return $this->belongsTo(ProjectRole::class);
    }

    public function sourceApprovalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }
}
