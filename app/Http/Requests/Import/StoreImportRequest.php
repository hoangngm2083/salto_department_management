<?php

namespace App\Http\Requests\Import;

use App\Enums\ImportType;
use App\Services\Import\CsvReader;
use App\Services\Import\ImportStrategyResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreImportRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ImportType::class)],
            'file' => ['required', 'file'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateFileConstraints($validator);
        });
    }

    /**
     * FR-2: extension, size, empty, UTF-8, header, and record-count checks.
     * Every check streams the file rather than loading it whole.
     */
    private function validateFileConstraints(Validator $validator): void
    {
        $file = $this->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = config('imports.allowed_extensions');

        if (! in_array($extension, $allowedExtensions, true)) {
            $validator->errors()->add('file', 'The file must be one of the following types: '.implode(', ', $allowedExtensions).'.');

            return;
        }

        if ($file->getSize() === 0) {
            $validator->errors()->add('file', 'The file must not be empty.');

            return;
        }

        $maxBytes = (int) config('imports.max_file_size_mb') * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            $validator->errors()->add('file', 'The file must not be larger than '.config('imports.max_file_size_mb').' MB.');

            return;
        }

        $reader = new CsvReader($file->getRealPath());

        if (! $reader->hasUtf8Encoding()) {
            $validator->errors()->add('file', 'The file must be UTF-8 encoded.');

            return;
        }

        $type = ImportType::from($this->input('type'));
        $handler = app(ImportStrategyResolver::class)->resolve($type);

        $expectedHeaders = $handler->headers();
        sort($expectedHeaders);

        $actualHeaders = $reader->headers();
        sort($actualHeaders);

        if ($actualHeaders !== $expectedHeaders) {
            $validator->errors()->add('file', 'The file headers are invalid. Expected: '.implode(', ', $handler->headers()).'.');

            return;
        }

        $scan = $reader->scan((int) config('imports.chunk_size'));

        if ($scan['total'] === 0) {
            $validator->errors()->add('file', 'The file must contain at least one record.');

            return;
        }

        if ($scan['total'] > (int) config('imports.max_records')) {
            $validator->errors()->add('file', 'The file exceeds the maximum of '.config('imports.max_records').' records.');
        }
    }
}
