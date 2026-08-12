<?php

namespace App\Services;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverKind;
use App\Events\ApprovalRequestDecided;
use App\Events\ApprovalStepActivated;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Services\Approval\ApprovalStateMachine;
use App\Services\Approval\ApprovedRequestHandlerRegistry;
use App\Services\Approval\Contracts\ApprovableRequest;
use App\Services\Approval\Contracts\ApprovalWorkflow;
use App\Services\Approval\EmptyApprovalWorkflow;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApprovalRequestService
{
    public function __construct(
        private readonly ApprovalStateMachine $stateMachine,
        private readonly ApprovedRequestHandlerRegistry $handlers,
    ) {}

    /**
     * With no `mine`/`pending_my_approval` tab selected, admins see everything and everyone
     * else defaults to requests they submitted, requests about them, or requests where
     * they're the resolved approver of the active step. `mine`/`pending_my_approval` narrow
     * that down for FE tabs ("My requests" vs "Pending my approval") for admins too - an
     * admin bypasses `ApprovalRequestPolicy` and can act on *any* active step (see
     * `scopeEligibleApprover()`), but "pending my approval" must still mean "still needs a
     * decision", not "every request regardless of status".
     *
     * @param  array<string, mixed>  $data
     */
    public function getPaginated(Employee $actor, array $data): CursorPaginator
    {
        $query = ApprovalRequest::query()
            ->with(['requester:id,name', 'subjectEmployee:id,name', 'activeStep'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['workflow_type'] ?? null, fn ($q, $type) => $q->where('workflow_type', $type));

        if ($data['mine'] ?? false) {
            $query->where('requested_by', $actor->id);
        } elseif ($data['pending_my_approval'] ?? false) {
            $this->scopeEligibleApprover($query, $actor);
        } elseif ($actor->position !== 'admin') {
            $query->where(function (Builder $scope) use ($actor) {
                $scope->where('requested_by', $actor->id)
                    ->orWhere('subject_employee_id', $actor->id)
                    ->orWhere(fn (Builder $eligible) => $this->scopeEligibleApprover($eligible, $actor));
            });
        }

        return $query->orderBy('id', 'desc')
            ->cursorPaginate($data['per_page'] ?? config('pagination.default_per_page'));
    }

    /**
     * Generic submission orchestrator, shared by every concrete workflow's own submit
     * action (e.g. a future SubmitRoleChangeRequestAction). Not called by anything yet in
     * Phase D - the first real caller is Phase E's role-change-request submission.
     */
    public function submit(
        Employee $actor,
        Employee $subjectEmployee,
        ApprovableRequest&Model $requestable,
        ApprovalWorkflow $workflow,
    ): ApprovalRequest {
        return DB::transaction(function () use ($actor, $subjectEmployee, $requestable, $workflow) {
            $approval = ApprovalRequest::create([
                'requestable_type' => $requestable::class,
                'requestable_id' => $requestable->getKey(),
                'workflow_type' => $workflow->type(),
                'requested_by' => $actor->id,
                'subject_employee_id' => $subjectEmployee->id,
                'status' => ApprovalStatus::Submitted,
                'current_step_order' => 1,
                'submitted_at' => now(),
            ]);

            $firstStep = null;

            foreach ($workflow->steps($requestable) as $index => $definition) {
                $step = $approval->steps()->create([
                    'step_order' => $index + 1,
                    'approver_kind' => $definition->approverKind,
                    'approver_employee_id' => $definition->approverEmployeeId,
                    'required_permission' => $definition->requiredPermission,
                    'status' => $index === 0 ? ApprovalStepStatus::Active : ApprovalStepStatus::Pending,
                ]);

                $firstStep ??= $step;
            }

            if ($firstStep === null) {
                throw new EmptyApprovalWorkflow($workflow->type());
            }

            ApprovalStepActivated::dispatch($approval, $firstStep);

            return $approval->fresh(['steps']);
        });
    }

    /**
     * Approves the currently active step. If it was the last step, attempts to apply the
     * business change immediately (Approved -> Applied|Failed); otherwise activates the
     * next step (-> InReview).
     */
    public function approve(Employee $actor, ApprovalRequest $approval, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $approval, $comment) {
            $approval = ApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->stateMachine->ensureCanApprove($approval);

            $activeStep = $approval->steps()->where('status', ApprovalStepStatus::Active)->lockForUpdate()->firstOrFail();

            $activeStep->update([
                'status' => ApprovalStepStatus::Approved,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            $this->recordAction($approval, $activeStep, $actor, ApprovalActionType::Approve, $comment);

            $nextStep = $approval->steps()->where('step_order', $activeStep->step_order + 1)->first();

            if ($nextStep !== null) {
                $nextStep->update(['status' => ApprovalStepStatus::Active]);
                $approval->update([
                    'status' => ApprovalStatus::InReview,
                    'current_step_order' => $nextStep->step_order,
                ]);

                ApprovalStepActivated::dispatch($approval, $nextStep);

                return $approval->fresh(['steps']);
            }

            $approval->update(['status' => ApprovalStatus::Approved, 'approved_at' => now()]);

            $approval = $this->apply($approval);

            ApprovalRequestDecided::dispatch($approval);

            return $approval;
        });
    }

    public function reject(Employee $actor, ApprovalRequest $approval, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $approval, $comment) {
            $approval = ApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->stateMachine->ensureCanReject($approval);

            $activeStep = $approval->steps()->where('status', ApprovalStepStatus::Active)->lockForUpdate()->firstOrFail();

            $activeStep->update([
                'status' => ApprovalStepStatus::Rejected,
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            $this->recordAction($approval, $activeStep, $actor, ApprovalActionType::Reject, $comment);

            $approval->update(['status' => ApprovalStatus::Rejected, 'rejected_at' => now()]);

            ApprovalRequestDecided::dispatch($approval);

            return $approval->fresh(['steps']);
        });
    }

    /**
     * Self-service withdrawal by the requester. Any not-yet-resolved active step is
     * marked Skipped rather than left dangling.
     */
    public function cancel(Employee $actor, ApprovalRequest $approval, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $approval, $comment) {
            $approval = ApprovalRequest::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->stateMachine->ensureCanCancel($approval);

            $activeStep = $approval->steps()->where('status', ApprovalStepStatus::Active)->first();

            if ($activeStep !== null) {
                $activeStep->update(['status' => ApprovalStepStatus::Skipped]);
                $this->recordAction($approval, $activeStep, $actor, ApprovalActionType::Cancel, $comment);
            }

            $approval->update(['status' => ApprovalStatus::Cancelled]);

            return $approval->fresh(['steps']);
        });
    }

    /**
     * Attempt to apply the business change for a fully-approved request. Success
     * transitions to Applied; a thrown exception from the handler is caught and recorded
     * as Failed rather than left to bubble as a 500 - an approved request can still fail
     * to apply if underlying data went stale between approval and this point, per the
     * documented APPROVED-vs-APPLIED distinction.
     */
    private function apply(ApprovalRequest $approval): ApprovalRequest
    {
        try {
            $this->handlers->get($approval->workflow_type)->apply($approval);
        } catch (Throwable $e) {
            $approval->update([
                'status' => ApprovalStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);

            return $approval->fresh(['steps']);
        }

        $approval->update(['status' => ApprovalStatus::Applied, 'applied_at' => now()]);

        return $approval->fresh(['steps']);
    }

    private function recordAction(
        ApprovalRequest $approval,
        ApprovalStep $step,
        Employee $actor,
        ApprovalActionType $type,
        ?string $comment,
    ): void {
        $approval->actions()->create([
            'approval_step_id' => $step->id,
            'actor_employee_id' => $actor->id,
            'action_type' => $type,
            'comment' => $comment,
        ]);
    }

    /**
     * An admin bypasses `ApprovalRequestPolicy` entirely (see its `before()`) and can act on
     * any active step regardless of its `ApproverKind`, so for an admin actor "eligible
     * approver" just means "the request still has an active step" - anything else has
     * already been fully decided by someone else and shouldn't show up as pending.
     *
     * @param  Builder<ApprovalRequest>  $query
     * @return Builder<ApprovalRequest>
     */
    private function scopeEligibleApprover(Builder $query, Employee $actor): Builder
    {
        if ($actor->position === 'admin') {
            return $query->whereHas('activeStep');
        }

        return $query->whereHas('activeStep', function (Builder $step) use ($actor) {
            $step->where('approver_employee_id', $actor->id);

            if ($actor->position === 'manager') {
                $step->orWhere(function (Builder $pool) use ($actor) {
                    $pool->whereNull('approver_employee_id')
                        ->where('approver_kind', ApproverKind::DepartmentManager)
                        ->whereHas(
                            'approvalRequest.subjectEmployee',
                            fn (Builder $subject) => $subject->where('department_id', $actor->department_id)
                        );
                });
            }
        });
    }
}
