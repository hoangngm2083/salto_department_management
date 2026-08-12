<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Jobs\Import\ProcessImportJob;
use App\Models\Employee;
use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImportService
{
    /**
     * Store the uploaded file, create the Import record, and dispatch
     * processing to the queue. Never processes the file inline (FR-3).
     */
    public function initiate(ImportType $type, UploadedFile $file, Employee $employee): Import
    {
        $folder = 'imports/'.date('Y/m/d');

        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $sluggedName = Str::slug($filename.'-'.Str::random(8));
        $finalFilename = $extension ? "{$sluggedName}.{$extension}" : $sluggedName;

        $path = $file->storeAs($folder, $finalFilename, config('imports.disk'));

        $import = Import::create([
            'type' => $type,
            'status' => ImportStatus::Queued,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'imported_by' => $employee->id,
        ]);

        ProcessImportJob::dispatch($import->id)->onQueue(config('imports.queue'));

        return $import;
    }
}
