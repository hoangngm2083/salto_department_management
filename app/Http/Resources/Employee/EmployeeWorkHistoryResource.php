<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWorkHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee' => [
                'id' => $this->id,
                'name' => $this->name,
                'department' => $this->department?->name,
                'current_level' => $this->currentLevel?->name,
            ],
            'projects' => $this->projectAssignments->map(fn ($assignment) => [
                'project_id' => $assignment->project->id,
                'project' => $assignment->project->name,
                'project_slug' => $assignment->project->slug,
                'project_status' => $assignment->project->status->value,
                'project_end_date' => $assignment->project->end_date?->toDateString(),
                'start_date' => $assignment->start_date?->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
                'status' => $assignment->status->value,
                'roles' => $assignment->rolePeriods->map(fn ($period) => [
                    'role' => $period->projectRole->name,
                    'start_date' => $period->start_date?->toDateString(),
                    'end_date' => $period->end_date?->toDateString(),
                ])->values(),
            ])->values(),
        ];
    }
}
