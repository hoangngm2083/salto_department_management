<?php

namespace App\Http\Resources\ProjectAssignment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentRolePeriodResource extends JsonResource
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
            'project_assignment_id' => $this->project_assignment_id,
            'project_role_id' => $this->project_role_id,
            'project_role_name' => $this->projectRole?->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}
