<?php

namespace App\Services;

use App\Enums\ExportType;
use App\Services\Export\ExportStrategyResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(private readonly ExportStrategyResolver $resolver)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function stream(ExportType $type, array $filters): StreamedResponse
    {
        $handler = $this->resolver->resolve($type);
        $filename = sprintf('%ss_export_%s.csv', $type->value, now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($handler, $filters): void {
            set_time_limit(0);

            $handle = fopen('php://output', 'wb');
            $handler->writeTo($handle, $filters);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            // Tells Nginx (when proxying via fastcgi_pass/proxy_pass) to relay each
            // flush() immediately instead of buffering the full response before
            // sending it to the client. No effect outside Nginx.
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
