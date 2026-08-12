import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { getApproval, updateApproval } from '../api/approvals';
import { getRoleChangeRequest } from '../api/roleChangeRequests';
import { useAuth } from '../context/useAuth';
import useAsyncResource from '../hooks/useAsyncResource';
import {
  APPROVAL_STATUS_BADGE_CLASSES,
  APPROVAL_STATUS_LABELS,
  APPROVAL_STEP_STATUS_BADGE_CLASSES,
  APPROVAL_STEP_STATUS_LABELS,
} from '../lib/approval-status';
import { ROLE_CHANGE_MODE_LABELS, WORKFLOW_TYPE_LABELS } from '../lib/workflow-type';

const ACTION_MESSAGES = {
  approve: 'Đã duyệt yêu cầu.',
  reject: 'Đã từ chối yêu cầu.',
  cancel: 'Đã hủy yêu cầu.',
};

/**
 * Generic approval detail + step timeline + approve/reject/cancel, plus - only for
 * project_role_change today - the business fields fetched from role-change-requests. Future
 * workflow types (F/G) would extend the `workflow_type === 'project_role_change'` branch below
 * with their own business-detail fetch/render, same pattern.
 *
 * "Duyệt"/"Từ chối" only render when the viewer resolves to the active step's approver via a
 * single known employee id (ProjectManager/DirectManager/SpecificEmployee) or the SystemAdmin
 * pool - pool kinds resolved dynamically server-side (DepartmentManager/Permission) have no
 * way to be checked client-side yet, so those steps show no buttons here even for an eligible
 * approver; the backend's own Gate::authorize is what actually protects the action regardless.
 */
export default function ApprovalDetailModal({ approvalId, onClose, onChanged }) {
  const { user } = useAuth();

  // Chained fetch (approval, then its role-change business detail if applicable) behind the
  // same requestIdRef staleness guard used by useCursorList - a slow response for a
  // previously-opened approvalId can never overwrite what's on screen for the current one.
  const { data, loading } = useAsyncResource({
    fetcher: () =>
      getApproval(approvalId).then(async (approval) => ({
        approval,
        roleChangeRequest:
          approval.workflow_type === 'project_role_change' ? await getRoleChangeRequest(approval.requestable_id) : null,
      })),
    deps: [approvalId],
  });

  const [approval, setApproval] = useState(null);
  const [comment, setComment] = useState('');
  const [acting, setActing] = useState(false);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- mirrors the fetched resource into local state that handleAction() then patches in place after approve/reject/cancel, without waiting for a refetch
    setApproval(data?.approval ?? null);
  }, [data]);

  const roleChangeRequest = data?.roleChangeRequest ?? null;

  async function handleAction(type) {
    setActing(true);

    try {
      const updated = await updateApproval(approval.id, { type, comment: comment || undefined });
      setApproval(updated);
      setComment('');
      toast.success(ACTION_MESSAGES[type]);
      onChanged?.(updated);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setActing(false);
    }
  }

  const activeStep = approval?.steps?.find((step) => step.status === 'active');
  const isResolvedApprover =
    activeStep &&
    (activeStep.approver_employee_id === user.id ||
      (activeStep.approver_employee_id === null && activeStep.approver_kind === 'system_admin' && user.position === 'admin'));
  const canDecide = isResolvedApprover && approval.requested_by !== user.id;
  const canCancel = approval && approval.requested_by === user.id && ['draft', 'submitted', 'in_review'].includes(approval.status);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" onClick={onClose}>
      <div
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Chi tiết yêu cầu phê duyệt</h2>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Đóng">
            &times;
          </button>
        </div>

        {loading && <ApprovalDetailSkeleton />}

        {!loading && approval && (
          <>
            <div className="mb-4 rounded-md border border-gray-200 p-3">
              <div className="mb-2 flex items-center justify-between">
                <span className="text-sm font-medium text-gray-900">
                  {WORKFLOW_TYPE_LABELS[approval.workflow_type] ?? approval.workflow_type}
                </span>
                <span
                  className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                    APPROVAL_STATUS_BADGE_CLASSES[approval.status] ?? 'bg-gray-100 text-gray-700'
                  }`}
                >
                  {APPROVAL_STATUS_LABELS[approval.status] ?? approval.status}
                </span>
              </div>

              {roleChangeRequest && (
                <div className="space-y-1 text-sm text-gray-600">
                  <p>
                    <span className="text-gray-400">Nhân viên:</span> {roleChangeRequest.employee_name} ·{' '}
                    {roleChangeRequest.project_name}
                  </p>
                  <p>
                    <span className="text-gray-400">
                      {ROLE_CHANGE_MODE_LABELS[roleChangeRequest.change_mode] ?? roleChangeRequest.change_mode}:
                    </span>{' '}
                    {roleChangeRequest.from_role_name ?? '—'} &rarr; {roleChangeRequest.to_role_name ?? '—'}
                  </p>
                  <p>
                    <span className="text-gray-400">Lý do:</span> {roleChangeRequest.reason}
                  </p>
                  <p>
                    <span className="text-gray-400">Người gửi:</span> {roleChangeRequest.created_by_name}
                  </p>
                </div>
              )}

              {approval.status === 'failed' && approval.failure_reason && (
                <p className="mt-2 text-xs text-red-600">Lỗi áp dụng: {approval.failure_reason}</p>
              )}
            </div>

            <div className="mb-4">
              <h3 className="mb-2 text-xs font-medium text-gray-500">Các bước duyệt</h3>
              <ol className="space-y-2">
                {approval.steps.map((step) => (
                  <li key={step.id} className="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2 text-sm">
                    <div>
                      <span className="font-medium text-gray-900">Bước {step.step_order}</span>{' '}
                      <span className="text-gray-500">{step.approver_name ?? 'Admin hệ thống'}</span>
                      {step.comment && <p className="text-xs text-gray-400">"{step.comment}"</p>}
                    </div>
                    <span
                      className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${
                        APPROVAL_STEP_STATUS_BADGE_CLASSES[step.status] ?? 'bg-gray-100 text-gray-500'
                      }`}
                    >
                      {APPROVAL_STEP_STATUS_LABELS[step.status] ?? step.status}
                    </span>
                  </li>
                ))}
              </ol>
            </div>

            {(canDecide || canCancel) && (
              <div>
                <label className="mb-3 block">
                  <span className="mb-1 block text-sm font-medium text-gray-700">Ghi chú (không bắt buộc)</span>
                  <textarea
                    rows={2}
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  />
                </label>

                <div className="flex justify-end gap-2">
                  {canCancel && (
                    <button
                      type="button"
                      onClick={() => handleAction('cancel')}
                      disabled={acting}
                      className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                    >
                      Hủy yêu cầu
                    </button>
                  )}
                  {canDecide && (
                    <>
                      <button
                        type="button"
                        onClick={() => handleAction('reject')}
                        disabled={acting}
                        className="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                      >
                        Từ chối
                      </button>
                      <button
                        type="button"
                        onClick={() => handleAction('approve')}
                        disabled={acting}
                        className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
                      >
                        Duyệt
                      </button>
                    </>
                  )}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

/**
 * Reserves roughly the same footprint as the loaded detail block + step timeline, so the
 * fixed/centered modal overlay doesn't visibly reflow around the user's cursor once the
 * fetch resolves.
 */
function ApprovalDetailSkeleton() {
  return (
    <div className="animate-pulse">
      <div className="mb-4 rounded-md border border-gray-200 p-3">
        <div className="mb-3 flex items-center justify-between">
          <div className="h-4 w-32 rounded bg-gray-200" />
          <div className="h-4 w-16 rounded-full bg-gray-200" />
        </div>
        <div className="space-y-2">
          <div className="h-3 w-3/4 rounded bg-gray-100" />
          <div className="h-3 w-1/2 rounded bg-gray-100" />
          <div className="h-3 w-2/3 rounded bg-gray-100" />
        </div>
      </div>

      <div className="mb-4">
        <div className="mb-2 h-3 w-20 rounded bg-gray-200" />
        <div className="space-y-2">
          <div className="h-10 rounded-md border border-gray-100 bg-gray-50" />
          <div className="h-10 rounded-md border border-gray-100 bg-gray-50" />
        </div>
      </div>

      <div className="flex justify-end gap-2">
        <div className="h-8 w-20 rounded-md bg-gray-100" />
        <div className="h-8 w-20 rounded-md bg-gray-100" />
      </div>
    </div>
  );
}
