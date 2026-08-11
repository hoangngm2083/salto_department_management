<?php

namespace App\Http\Resources\Approval;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
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
            'workflow_type' => $this->workflow_type->value,
            'requested_by' => $this->requested_by,
            'requester_name' => $this->requester?->name,
            'subject_employee_id' => $this->subject_employee_id,
            'subject_employee_name' => $this->subjectEmployee?->name,
            'status' => $this->status->value,
            'current_step_order' => $this->current_step_order,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'applied_at' => $this->applied_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'failure_reason' => $this->failure_reason,
            'steps' => ApprovalStepResource::collection($this->whenLoaded('steps')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
