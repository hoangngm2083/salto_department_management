<?php

namespace App\Http\Resources\RoleChangeRequest;

use App\Http\Resources\Approval\ApprovalRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleChangeRequestResource extends JsonResource
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
            'project_id' => $this->projectAssignment?->project_id,
            'project_name' => $this->projectAssignment?->project?->name,
            'project_slug' => $this->projectAssignment?->project?->slug,
            'employee_id' => $this->projectAssignment?->employee_id,
            'employee_name' => $this->projectAssignment?->employee?->name,
            'change_mode' => $this->change_mode->value,
            'from_project_role_id' => $this->from_project_role_id,
            'from_role_name' => $this->fromRole?->name,
            'to_project_role_id' => $this->to_project_role_id,
            'to_role_name' => $this->toRole?->name,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toISOString(),
            'approval_request' => new ApprovalRequestResource($this->whenLoaded('approvalRequest')),
        ];
    }
}
