<?php

namespace App\Http\Requests\LeaveRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequestStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only admins may revert a leave request back to pending; everyone else may only move
     * it forward to approved/rejected/cancelled.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedStatuses = $this->user()?->position === 'admin'
            ? ['pending', 'approved', 'rejected', 'cancelled']
            : ['approved', 'rejected', 'cancelled'];

        return [
            'status' => ['required', 'string', Rule::in($allowedStatuses)],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
