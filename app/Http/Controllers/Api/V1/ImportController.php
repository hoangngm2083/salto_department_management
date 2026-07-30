<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\StoreImportRequest;
use App\Http\Resources\Import\ImportResource;
use App\Models\Employee;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        Gate::authorize('create', Import::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $import = $this->importService->initiate(
            ImportType::from($request->validated('type')),
            $request->file('file'),
            $actor,
        );

        return $this->successResponse(
            new ImportResource($import),
            'Import queued.',
            202
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Import $import): JsonResponse
    {
        Gate::authorize('view', $import);

        return $this->successResponse(
            new ImportResource($import->load('errors')),
            'Import status retrieved.'
        );
    }
}
