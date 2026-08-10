<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project->name),
            'project_slug' => $this->whenLoaded('project', fn () => $this->project->slug),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->assignee?->name,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->reviewer?->name,
            'due_date' => $this->due_date?->toDateString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_note' => $this->review_note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
