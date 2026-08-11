<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\ProjectAssignmentStatus;
use App\Enums\ProjectStatus;
use App\Models\AssignmentRolePeriod;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectAssignmentService
{
    /**
     * Get paginated assignments for a project using cursor pagination (no OFFSET).
     */
    public function getPaginated(Project $project, array $data): CursorPaginator
    {
        return $project->assignments()
            ->with(['employee:id,name', 'rolePeriods.projectRole:id,name,slug'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Create an assignment for one or more employees on the project, each
     * with the same initial role period(s), atomically. Either every
     * employee ends up assigned or none do (all-or-nothing) — a single
     * employee already having an active assignment rolls the whole batch
     * back rather than silently skipping just that one. Assignments are
     * created directly Active (no approval flow exists yet).
     *
     * @return EloquentCollection<int, ProjectAssignment>
     */
    public function create(Project $project, array $data, Employee $actor): EloquentCollection
    {
        if (! in_array($project->status, [ProjectStatus::Planned, ProjectStatus::Active], true)) {
            throw ValidationException::withMessages([
                'project' => ['Cannot add a member to a project that is not planned or active.'],
            ]);
        }

        $startDate = $data['start_date'] ?? today()->toDateString();

        if ($project->end_date !== null && $startDate > $project->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'start_date' => ['Assignment cannot start after the project end date.'],
            ]);
        }

        return DB::transaction(function () use ($project, $data, $actor, $startDate) {
            $employeeIds = $data['employee_ids'];

            $alreadyActiveIds = $project->assignments()
                ->whereIn('employee_id', $employeeIds)
                ->where('status', ProjectAssignmentStatus::Active)
                ->lockForUpdate()
                ->pluck('employee_id');

            if ($alreadyActiveIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'employee_ids' => [
                        'These employees already have an active assignment on the project: '
                        .$alreadyActiveIds->implode(', ').'.',
                    ],
                ]);
            }

            $assignments = new EloquentCollection;

            foreach ($employeeIds as $employeeId) {
                $assignment = $project->assignments()->create([
                    'employee_id' => $employeeId,
                    'start_date' => $startDate,
                    'status' => ProjectAssignmentStatus::Active,
                    'assigned_by' => $actor->id,
                ]);

                foreach ($data['role_ids'] as $roleId) {
                    $assignment->rolePeriods()->create([
                        'project_role_id' => $roleId,
                        'start_date' => $startDate,
                    ]);
                }

                $assignments->push($assignment);
            }

            return $assignments->load(['employee:id,name', 'rolePeriods.projectRole:id,name,slug']);
        });
    }

    /**
     * End an assignment today: closes the assignment itself and every role
     * period still active on it, so it never ends up "ended" while a role
     * period on it stays open.
     */
    public function end(ProjectAssignment $assignment): ProjectAssignment
    {
        return DB::transaction(function () use ($assignment) {
            $assignment = ProjectAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $today = today()->toDateString();

            $assignment->update([
                'end_date' => $today,
                'status' => ProjectAssignmentStatus::Ended,
            ]);

            $assignment->activeRolePeriods()->update(['end_date' => $today]);

            return $assignment->fresh(['employee:id,name', 'rolePeriods.projectRole:id,name,slug']);
        });
    }

    /**
     * Add a role period to an active assignment. `source_approval_request_id` is optional -
     * set by ApplyRoleChangeHandler (Phase E) to trace which approval created the period,
     * left null for the direct admin/PM "add role" action this method also serves.
     */
    public function addRole(ProjectAssignment $assignment, array $data): AssignmentRolePeriod
    {
        return DB::transaction(function () use ($assignment, $data) {
            if ($assignment->status !== ProjectAssignmentStatus::Active) {
                throw ValidationException::withMessages([
                    'project_role_id' => ['Cannot add a role to an assignment that is not active.'],
                ]);
            }

            $alreadyActive = $assignment->rolePeriods()
                ->where('project_role_id', $data['project_role_id'])
                ->whereNull('end_date')
                ->lockForUpdate()
                ->exists();

            if ($alreadyActive) {
                throw ValidationException::withMessages([
                    'project_role_id' => ['This role is already active for the assignment.'],
                ]);
            }

            $rolePeriod = $assignment->rolePeriods()->create([
                'project_role_id' => $data['project_role_id'],
                'start_date' => $data['start_date'] ?? today()->toDateString(),
                'source_approval_request_id' => $data['source_approval_request_id'] ?? null,
            ]);

            return $rolePeriod->load('projectRole:id,name,slug');
        });
    }

    /**
     * End a role period today.
     */
    public function endRole(AssignmentRolePeriod $rolePeriod): AssignmentRolePeriod
    {
        $rolePeriod->update(['end_date' => today()->toDateString()]);

        return $rolePeriod->fresh('projectRole:id,name,slug');
    }

    /**
     * Search the project's active members - employees with an active
     * assignment on the project - by name and/or project role, for the
     * "Members" tab and the task-assignment autocomplete alike.
     */
    public function searchMembers(Project $project, array $data): CursorPaginator
    {
        return Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->whereHas(
                'projectAssignments',
                fn ($query) => $query->where('project_id', $project->id)->where('status', ProjectAssignmentStatus::Active)
            )
            ->with([
                'currentLevel:id,name',
                'projectAssignments' => fn ($query) => $query->where('project_id', $project->id)
                    ->where('status', ProjectAssignmentStatus::Active)
                    ->with('activeRolePeriods.projectRole:id,name'),
            ])
            ->when($data['name'] ?? null, fn ($query, $name) => $query->nameContains($name))
            ->when($data['project_role_id'] ?? null, fn ($query, $roleId) => $query->whereHas(
                'projectAssignments',
                fn ($qq) => $qq->where('project_id', $project->id)
                    ->whereHas('activeRolePeriods', fn ($qqq) => $qqq->where('project_role_id', $roleId))
            ))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Build the read-model combining an employee's projects and, within
     * each, their role periods over time — current and past.
     */
    public function workHistory(Employee $employee, bool $activeOnly = false): Employee
    {
        return $employee->load([
            'department:id,name',
            'currentLevel:id,name',
            'projectAssignments' => fn ($query) => $query
                ->when($activeOnly, fn ($query) => $query->whereNull('end_date'))
                ->orderBy('start_date', 'desc'),
            'projectAssignments.project:id,name,slug,status,end_date',
            'projectAssignments.rolePeriods' => fn ($query) => $query->orderBy('start_date', 'desc'),
            'projectAssignments.rolePeriods.projectRole:id,name',
        ]);
    }
}
