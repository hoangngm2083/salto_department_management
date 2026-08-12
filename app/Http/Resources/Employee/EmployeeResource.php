<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'department_slug' => $this->department?->slug,
            'current_level_id' => $this->current_level_id,
            'current_level_name' => $this->currentLevel?->name,
            'manager_employee_id' => $this->manager_employee_id,
            'manager_name' => $this->manager?->name,
            'name' => $this->name,
            'email' => $this->email,
            'birthday' => $this->birthday?->toDateString(),
            'position' => $this->position,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
