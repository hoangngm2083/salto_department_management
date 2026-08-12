<?php

namespace App\Jobs\Import;

use App\Models\Import;
use App\Services\Import\ImportStrategyResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public readonly int $importId)
    {
        $this->tries = (int) config('imports.max_retry');
        $this->timeout = (int) config('imports.timeout');
    }

    public function handle(ImportStrategyResolver $resolver): void
    {
        $import = Import::findOrFail($this->importId);

        $resolver->resolve($import->type)->run($import);
    }
}
