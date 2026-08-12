<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\GetEmployeesRequest;
use App\Http\Requests\Employee\GetEmployeeWorkHistoryRequest;
use App\Http\Requests\Employee\UpsertEmployeeRequest;
use App\Http\Requests\Task\GetEmployeeTasksRequest;
use App\Http\Resources\Employee\EmployeeCollection;
use App\Http\Resources\Employee\EmployeeResource;
use App\Http\Resources\Employee\EmployeeWorkHistoryResource;
use App\Http\Resources\Project\ProjectResource;
use App\Http\Resources\Task\TaskCollection;
use App\Http\Resources\Task\TaskResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\ProjectAssignmentService;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly ProjectAssignmentService $projectAssignmentService,
        private readonly ProjectService $projectService,
        private readonly TaskService $taskService,
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(GetEmployeesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);
        $employees = $this->employeeService->getPaginated($request->validated());

        return $this->successResponse(
            new EmployeeCollection($employees),
            'Employees retrieved successfully.'
        );
    }

    /**
     * Count employees matching the same filters as `index()` - the dashboard
     * "Nhân viên" stat (mục 9.1) only needs a number.
     */
    public function count(GetEmployeesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        return $this->successResponse(
            ['total' => $this->employeeService->count($request->validated())],
            'Employee count retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $employee = $this->employeeService->upsert($actor, $request->validated());

        return $this->successResponse(
            new EmployeeResource($employee),
            'Employee created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $employee->loadMissing(['department:id,name,slug', 'currentLevel:id,name,slug', 'manager:id,name']);

        return $this->successResponse(
            new EmployeeResource($employee),
            'Employee retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertEmployeeRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        /** @var Employee $actor */
        $actor = $request->user();
        $employee = $this->employeeService->upsert($actor, $request->validated(), $employee);

        return $this->successResponse(
            new EmployeeResource($employee),
            'Employee updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);
        $this->employeeService->delete($employee);

        return $this->successResponse(
            null,
            'Employee deleted successfully.'
        );
    }

    /**
     * Display the employee's project/role work history.
     */
    public function workHistory(GetEmployeeWorkHistoryRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $employee = $this->projectAssignmentService->workHistory($employee, $request->boolean('active'));

        return $this->successResponse(
            new EmployeeWorkHistoryResource($employee),
            'Employee work history retrieved successfully.'
        );
    }

    /**
     * Display the employee's assigned tasks across every project ("my tasks").
     */
    public function tasks(GetEmployeeTasksRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $tasks = $this->taskService->getPaginatedForEmployee($employee, $request->validated());

        return $this->successResponse(
            new TaskCollection($tasks),
            'Employee tasks retrieved successfully.'
        );
    }

    /**
     * Display the projects the employee is currently an active manager of,
     * with progress counts - the dashboard "project tôi quản lý" widget
     * (plan mục 9.1), open to any employee since project managers aren't
     * restricted to the `manager` system role.
     */
    public function managedProjects(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $projects = $this->projectService->getManagedByEmployee($employee);

        return $this->successResponse(
            ProjectResource::collection($projects),
            'Employee managed projects retrieved successfully.'
        );
    }

    /**
     * Display overdue tasks (top 10) across every project the employee is currently an active
     * manager of - the dashboard "task quá hạn cần xử lý" widget (mục 7.4), mirrors managedProjects().
     */
    public function overdueManagedTasks(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);
        $tasks = $this->taskService->getOverdueForManagedProjects($employee);

        return $this->successResponse(
            TaskResource::collection($tasks),
            'Employee managed overdue tasks retrieved successfully.'
        );
    }
}
