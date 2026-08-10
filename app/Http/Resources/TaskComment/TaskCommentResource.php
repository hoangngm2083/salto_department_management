<?php

namespace App\Http\Resources\TaskComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCommentResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->name,
            'body' => $this->body,
            'task_status' => $this->task_status?->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
