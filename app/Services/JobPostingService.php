<?php

namespace App\Services;

use App\Enums\JobPostingStatus;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Services\Recruitment\JobPostingDispatcher;
use Illuminate\Contracts\Pagination\CursorPaginator;

class JobPostingService
{
    public function __construct(private readonly JobPostingDispatcher $dispatcher)
    {
        //
    }

    /**
     * Get paginated job postings with optional status/department filtering using
     * cursor pagination (no OFFSET). No default status filter (mirrors Project, not
     * Department/Level) - job postings have 4 lifecycle states, not a binary active/inactive.
     */
    public function getPaginated(array $filters, int $perPage): CursorPaginator
    {
        return JobPosting::query()
            ->with('channels')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy('id', 'desc')
            ->cursorPaginate($perPage);
    }

    /**
     * Create or update a job posting. Creation always starts Draft regardless of any
     * client-supplied `status` (mirrors TaskService::create() forcing Todo) - only the
     * update path below runs the transition logic. On update, a Draft → Published
     * transition stamps `published_at` and dispatches to every active recruitment
     * channel; a transition into Closed/Cancelled stamps `closed_at` if not already set.
     * UpsertJobPostingRequest rejects any status change once a posting is Closed/Cancelled,
     * so those are terminal and never reachable here as the "previous" status.
     */
    public function upsert(array $data, Employee $actor, ?JobPosting $jobPosting = null): JobPosting
    {
        if ($jobPosting === null) {
            return JobPosting::create([
                ...$data,
                'status' => JobPostingStatus::Draft,
                'created_by' => $actor->id,
            ]);
        }

        $previousStatus = $jobPosting->status;
        $jobPosting->update($data);
        $jobPosting = $jobPosting->fresh();

        if ($previousStatus === JobPostingStatus::Draft && $jobPosting->status === JobPostingStatus::Published) {
            $jobPosting->update(['published_at' => $jobPosting->published_at ?? now()]);
            $this->dispatcher->dispatch($jobPosting);
        } elseif (in_array($jobPosting->status, [JobPostingStatus::Closed, JobPostingStatus::Cancelled], true)
            && $jobPosting->closed_at === null) {
            $jobPosting->update(['closed_at' => now()]);
        }

        return $jobPosting->fresh();
    }

    /**
     * Delete a job posting record.
     */
    public function delete(JobPosting $jobPosting): bool
    {
        return (bool) $jobPosting->delete();
    }
}
