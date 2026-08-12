<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roles = $this->projectAssignments
            ->flatMap(fn ($assignment) => $assignment->activeRolePeriods->pluck('projectRole.name'))
            ->filter()
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->currentLevel?->name,
            'roles' => $roles,
        ];
    }
}
