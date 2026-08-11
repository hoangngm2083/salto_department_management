import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { getApproval, updateApproval } from '../api/approvals';
import { getRoleChangeRequest } from '../api/roleChangeRequests';
import { useAuth } from '../context/useAuth';
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
  const [approval, setApproval] = useState(null);
  const [roleChangeRequest, setRoleChangeRequest] = useState(null);
  const [loading, setLoading] = useState(true);
  const [comment, setComment] = useState('');
  const [acting, setActing] = useState(false);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setLoading(true);

    getApproval(approvalId)
      .then((data) => {
        setApproval(data);

        if (data.workflow_type === 'project_role_change') {
          return getRoleChangeRequest(data.requestable_id).then(setRoleChangeRequest);
        }

        return undefined;
      })
      .finally(() => setLoading(false));
  }, [approvalId]);

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

        {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

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
