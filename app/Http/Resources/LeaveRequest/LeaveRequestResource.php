<?php

namespace App\Http\Resources\LeaveRequest;

use App\Http\Resources\Approval\ApprovalRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->name,
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'project_slug' => $this->project?->slug,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
            'approval_request' => new ApprovalRequestResource($this->whenLoaded('approvalRequest')),
        ];
    }
}
