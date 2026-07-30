<?php

namespace App\Http\Resources\Import;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $processed = $this->created_count + $this->updated_count + $this->failed_count;

        return [
            'import_id' => (string) $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'progress' => $this->total > 0 ? (int) round($processed / $this->total * 100) : 0,
            'total' => $this->total,
            'processed' => $processed,
            'created' => $this->created_count,
            'updated' => $this->updated_count,
            'failed' => $this->failed_count,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'duration' => $this->finished_at !== null && $this->started_at !== null
                ? $this->finished_at->diffInSeconds($this->started_at)
                : null,
            'errors' => ImportErrorResource::collection($this->whenLoaded('errors')),
        ];
    }
}
