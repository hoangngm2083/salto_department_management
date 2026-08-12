<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'managers' => ProjectManagerResource::collection($this->managers),
            'total_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'done_count' => $this->when(isset($this->done_count), $this->done_count),
            'overdue_count' => $this->when(isset($this->overdue_count), $this->overdue_count),
            'todo_count' => $this->when(isset($this->todo_count), $this->todo_count),
            'in_progress_count' => $this->when(isset($this->in_progress_count), $this->in_progress_count),
            'in_review_count' => $this->when(isset($this->in_review_count), $this->in_review_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
