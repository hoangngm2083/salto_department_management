<?php

namespace App\Http\Resources\Approval;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalStepResource extends JsonResource
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
            'step_order' => $this->step_order,
            'approver_kind' => $this->approver_kind->value,
            'approver_employee_id' => $this->approver_employee_id,
            'approver_name' => $this->approverEmployee?->name,
            'status' => $this->status->value,
            'acted_by' => $this->acted_by,
            'actor_name' => $this->actor?->name,
            'acted_at' => $this->acted_at?->toISOString(),
            'comment' => $this->comment,
        ];
    }
}
