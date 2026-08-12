<?php

namespace App\Models;

use App\Enums\TaskDelayRequestStatus;
use Database\Factories\TaskDelayRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'requested_by', 'current_due_date', 'requested_due_date', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class TaskDelayRequest extends Model
{
    /** @use HasFactory<TaskDelayRequestFactory> */
    use HasFactory;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new TaskDelayRequest()`) has the same status the DB column default
     * would give a persisted row — the enum cast needs a real value, not null.
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
            'current_due_date' => 'date',
            'requested_due_date' => 'date',
            'status' => TaskDelayRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
}
