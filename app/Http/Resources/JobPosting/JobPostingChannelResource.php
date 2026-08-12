<?php

namespace App\Http\Resources\JobPosting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPostingChannelResource extends JsonResource
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
            'channel' => $this->channel,
            'status' => $this->status->value,
            'external_ref' => $this->external_ref,
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'error_message' => $this->error_message,
        ];
    }
}
