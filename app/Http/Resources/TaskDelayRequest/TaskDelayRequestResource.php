<?php

namespace App\Http\Resources\TaskDelayRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDelayRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task_title' => $this->task?->title,
            'project_id' => $this->task?->project_id,
            'requested_by' => $this->requested_by,
            'requester_name' => $this->requester?->name,
            'current_due_date' => $this->current_due_date?->toDateString(),
            'requested_due_date' => $this->requested_due_date?->toDateString(),
            'reason' => $this->reason,
            'status' => $this->status->value,
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->reviewer?->name,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
