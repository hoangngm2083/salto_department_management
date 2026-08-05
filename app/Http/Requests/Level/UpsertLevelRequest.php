<?php

namespace App\Http\Requests\Level;

use App\Models\Level;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge([
                'slug' => Str::slug($this->input('name')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $level = $this->route('level');
        $levelId = $level instanceof Level ? $level->id : $level;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'slug')->ignore($levelId),
            ],
            'rank' => [
                'required',
                'integer',
                'min:0',
                'max:65535',
                Rule::unique('levels', 'rank')->ignore($levelId),
            ],
            'probation_salary_percentage' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
