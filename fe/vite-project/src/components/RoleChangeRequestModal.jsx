import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { createRoleChangeRequest } from '../api/roleChangeRequests';
import { listProjectRoles } from '../api/projectRoles';
import { ROLE_CHANGE_MODE_LABELS } from '../lib/workflow-type';

/**
 * Submits a Phase E role change request (ADD/REPLACE/REMOVE) for one project assignment.
 * "Current role" options come from the assignment's own active role periods (already known,
 * no extra fetch); "new role" options come from the full active project_roles list, minus
 * whatever's already active on the assignment - mirrors the ADD/REPLACE server-side rule that
 * a target role can't already be active. Approval/status is never shown here - this only
 * creates the request; its progress is tracked on the /approvals page.
 */
export default function RoleChangeRequestModal({ assignment, onClose, onCreated }) {
  const activeRolePeriods = assignment.role_periods.filter((period) => !period.end_date);
  const activeRoleIds = activeRolePeriods.map((period) => period.project_role_id);

  const [mode, setMode] = useState('add');
  const [fromRoleId, setFromRoleId] = useState('');
  const [toRoleId, setToRoleId] = useState('');
  const [reason, setReason] = useState('');
  const [roleOptions, setRoleOptions] = useState([]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    listProjectRoles({ status: 'active', per_page: 100 })
      .then((res) => setRoleOptions(res.data))
      .catch(() => {});
  }, []);

  function handleModeChange(nextMode) {
    setMode(nextMode);
    setFromRoleId('');
    setToRoleId('');
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const created = await createRoleChangeRequest({
        project_assignment_id: assignment.id,
        change_mode: mode,
        from_project_role_id: mode === 'add' ? undefined : Number(fromRoleId),
        to_project_role_id: mode === 'remove' ? undefined : Number(toRoleId),
        reason,
      });
      toast.success('Đã gửi yêu cầu đổi vai trò, chờ project manager duyệt.');
      onCreated?.(created);
      onClose();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSubmitting(false);
    }
  }

  const toRoleOptions = roleOptions.filter((role) => !activeRoleIds.includes(role.id));

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" onClick={onClose}>
      <div
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Yêu cầu đổi vai trò</h2>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Đóng">
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Hình thức</span>
            <select
              value={mode}
              onChange={(e) => handleModeChange(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              {Object.entries(ROLE_CHANGE_MODE_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>

          {mode !== 'add' && (
            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Vai trò hiện tại</span>
              <select
                required
                value={fromRoleId}
                onChange={(e) => setFromRoleId(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                <option value="">Chọn vai trò...</option>
                {activeRolePeriods.map((period) => (
                  <option key={period.project_role_id} value={period.project_role_id}>
                    {period.project_role_name}
                  </option>
                ))}
              </select>
            </label>
          )}

          {mode !== 'remove' && (
            <label className="mb-6 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Vai trò mới</span>
              <select
                required
                value={toRoleId}
                onChange={(e) => setToRoleId(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                <option value="">Chọn vai trò...</option>
                {toRoleOptions.map((role) => (
                  <option key={role.id} value={role.id}>
                    {role.name}
                  </option>
                ))}
              </select>
            </label>
          )}

          <label className="mb-6 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Lý do</span>
            <textarea
              required
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
            >
              Hủy
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
            >
              {submitting ? 'Đang gửi...' : 'Gửi yêu cầu'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
