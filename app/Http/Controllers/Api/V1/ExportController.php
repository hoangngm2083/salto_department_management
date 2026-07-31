<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Export\GetExportRequest;
use App\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exportService)
    {
        //
    }

    /**
     * Stream a CSV export of the requested resource type.
     */
    public function download(GetExportRequest $request): StreamedResponse
    {
        return $this->exportService->stream($request->getType(), $request->getFilters());
    }
}
