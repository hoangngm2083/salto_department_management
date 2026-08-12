<?php

namespace App\Http\Resources\JobPosting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPostingResource extends JsonResource
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
            'department_id' => $this->department_id,
            'project_id' => $this->project_id,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'employment_type' => $this->employment_type->value,
            'slots_needed' => $this->slots_needed,
            'status' => $this->status->value,
            'created_by' => $this->created_by,
            'published_at' => $this->published_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'channels' => JobPostingChannelResource::collection($this->whenLoaded('channels')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
