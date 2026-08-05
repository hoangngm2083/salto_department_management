<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Events\LeaveRequestReviewed;
use App\Events\LeaveRequestSubmitted;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Pagination\CursorPaginator;

class LeaveRequestService
{
    /**
     * Get paginated leave requests with filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(array $data): CursorPaginator
    {
        return LeaveRequest::query()
            ->with(['employee:id,name,department_id', 'employee.department:id,name', 'reviewer:id,name'])
            ->when($data['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($data['department_id'] ?? null, fn ($query, $departmentId) => $query->whereRelation('employee', 'department_id', $departmentId))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Create a leave request for the requesting employee.
     */
    public function create(Employee $actor, array $data): LeaveRequest
    {
        $leaveRequest = LeaveRequest::create([
            ...$data,
            'employee_id' => $actor->id,
            'status' => LeaveRequestStatus::Pending,
        ]);

        $leaveRequest->load(['employee:id,name,department_id', 'employee.department:id,name']);

        LeaveRequestSubmitted::dispatch($leaveRequest);

        return $leaveRequest;
    }

    /**
     * Transition a leave request's status. Cancelling is a self-service action and isn't
     * recorded as a review; approving/rejecting stamps the acting manager/admin as reviewer;
     * reverting to pending (admin-only) clears any prior review so the request reads as
     * genuinely unreviewed again.
     */
    public function updateStatus(Employee $actor, LeaveRequest $leaveRequest, array $data): LeaveRequest
    {
        $attributes = match ($data['status']) {
            LeaveRequestStatus::Pending->value => [
                'status' => LeaveRequestStatus::Pending,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ],
            LeaveRequestStatus::Cancelled->value => [
                'status' => LeaveRequestStatus::Cancelled,
            ],
            default => [
                'status' => $data['status'],
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ],
        };

        $leaveRequest->update($attributes);

        $leaveRequest = $leaveRequest->fresh(['employee:id,name,department_id', 'employee.department:id,name', 'reviewer:id,name']);

        if (in_array($data['status'], [LeaveRequestStatus::Approved->value, LeaveRequestStatus::Rejected->value], true)) {
            LeaveRequestReviewed::dispatch($leaveRequest);
        }

        return $leaveRequest;
    }
}
