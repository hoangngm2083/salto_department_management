<?php

namespace App\Jobs\Import;

use App\Models\Import;
use App\Services\Import\ImportStrategyResolver;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    /**
     * Delay (seconds) before each retry. Bounded and increasing rather than
     * immediate/infinite: a chunk that fails because it raced another writer
     * for the same business key (unique-constraint violation or lock-wait
     * timeout) is expected to succeed once re-run against the now-settled
     * data, so a short wait is enough — no custom catch/retry logic needed,
     * this only widens the gap between attempts.
     *
     * @var list<int>
     */
    public array $backoff = [5, 10, 15, 30, 60];

    /**
     * Payload only carries a byte range into the source file — never the
     * row data itself, so the queued job stays tiny regardless of file size.
     */
    public function __construct(
        public readonly int $importId,
        public readonly string $filePath,
        public readonly int $startByte,
        public readonly int $rowCount,
        public readonly int $firstRowNumber,
    ) {
        $this->tries = (int) config('imports.max_retry');
    }

    public function handle(ImportStrategyResolver $resolver): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $import = Import::findOrFail($this->importId);

        $resolver->resolve($import->type)->processChunk(
            $import,
            $this->filePath,
            $this->startByte,
            $this->rowCount,
            $this->firstRowNumber,
        );
    }
}
