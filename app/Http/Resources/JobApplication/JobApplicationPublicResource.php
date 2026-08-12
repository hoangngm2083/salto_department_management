<?php

namespace App\Http\Resources\JobApplication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationPublicResource extends JsonResource
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
            'job_posting' => [
                'id' => $this->jobPosting->id,
                'title' => $this->jobPosting->title,
                'slug' => $this->jobPosting->slug,
            ],
            'status' => $this->status->value,
            'submitted_at' => $this->created_at?->toISOString(),
        ];
    }
}
