<?php

namespace App\Http\Resources\Career;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPostingPublicResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'department' => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ],
            'project' => $this->when($this->project_id !== null, fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'description' => $this->description,
            'requirements' => $this->requirements,
            'employment_type' => $this->employment_type->value,
            'slots_needed' => $this->slots_needed,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
