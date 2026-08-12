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
     * On create, `rank` is derived server-side from `insert_position` (+
     * `reference_level_id`) rather than typed in directly - see
     * `LevelService::resolveRankForInsert()`. Update keeps the direct
     * numeric `rank` field (reordering an existing level isn't part of this
     * change), so the two verbs need different rule sets.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $level = $this->route('level');
        $levelId = $level instanceof Level ? $level->id : $level;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'slug')->ignore($levelId),
            ],
            'probation_salary_percentage' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];

        if ($level === null) {
            $rules['insert_position'] = ['required', Rule::in(['start', 'end', 'before', 'after'])];
            $rules['reference_level_id'] = [
                Rule::requiredIf(fn () => in_array($this->input('insert_position'), ['before', 'after'], true)),
                'integer',
                Rule::exists('levels', 'id'),
            ];

            return $rules;
        }

        $rules['rank'] = [
            'required',
            'integer',
            'min:0',
            'max:65535',
            Rule::unique('levels', 'rank')->ignore($levelId),
        ];

        return $rules;
    }
}
