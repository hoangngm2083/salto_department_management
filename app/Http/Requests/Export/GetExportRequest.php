<?php

namespace App\Http\Requests\Export;

use App\Enums\ExportType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetExportRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ExportType::class)],

            // Department filters
            'status' => ['sometimes', 'filled', 'string', Rule::in(['active', 'inactive', 'all'])],

            // Employee filters
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
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'department_slug' => ['nullable', 'string', Rule::exists('departments', 'slug')],
        ];
    }

    /**
     * Get the export type.
     */
    public function getType(): ExportType
    {
        return ExportType::from($this->validated('type'));
    }

    /**
     * Get the type-specific filters, excluding `type` itself.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->safe()->except('type');
    }
}
