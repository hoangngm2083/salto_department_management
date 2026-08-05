<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Level\GetLevelsRequest;
use App\Http\Requests\Level\UpsertLevelRequest;
use App\Http\Resources\Level\LevelCollection;
use App\Http\Resources\Level\LevelResource;
use App\Models\Level;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LevelController extends Controller
{
    public function __construct(private readonly LevelService $levelService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetLevelsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Level::class);
        $levels = $this->levelService->getPaginated(
            $request->getStatus(),
            $request->getPerPage()
        );

        return $this->successResponse(
            new LevelCollection($levels),
            'Levels retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertLevelRequest $request): JsonResponse
    {
        Gate::authorize('create', Level::class);
        $level = $this->levelService->upsert($request->validated());

        return $this->successResponse(
            new LevelResource($level),
            'Level created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Level $level): JsonResponse
    {
        Gate::authorize('view', $level);

        return $this->successResponse(
            new LevelResource($level),
            'Level retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertLevelRequest $request, Level $level): JsonResponse
    {
        Gate::authorize('update', $level);
        $level = $this->levelService->upsert($request->validated(), $level);

        return $this->successResponse(
            new LevelResource($level),
            'Level updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Level $level): JsonResponse
    {
        Gate::authorize('delete', $level);
        $this->levelService->delete($level);

        return $this->successResponse(
            null,
            'Level deleted successfully.'
        );
    }
}
