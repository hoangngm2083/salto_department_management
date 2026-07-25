<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetEmployeesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize position from comma-separated string / JSON into an array.
     *
     * Supports:
     * - ?position=employee,manager
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('position') || ! is_string($this->position)) {
            return;
        }

        $this->merge([
            'position' => array_values(array_filter(array_map(
                'trim',
                explode(',', $this->position)
            ))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            /**
             * Filter by positions. Multiple values are comma-separated.
             *
             * @var string
             *
             * @example employee,manager
             */
            'position' => ['sometimes', 'nullable', 'array'],
            /** @ignoreParam */
            'position.*' => ['required', 'string', Rule::in(['employee', 'manager'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
