import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { getEmployeeWorkHistory } from '../api/employees';
import { createLeaveRequest } from '../api/leaveRequests';
import { useAuth } from '../context/useAuth';

/**
 * Project picker reuses GET /employees/{employee}/projects (workHistory, active=1) - the
 * same endpoint/shape MyProjectsPage.jsx already uses - instead of a new endpoint. Required
 * when the employee has at least one active assignment (mirrors LeaveRequestService's
 * "project_id required iff an active assignment exists" rule); hidden entirely otherwise,
 * since the backend workflow simply skips the PM step when there's nothing to pick.
 */
export default function CreateLeaveRequestModal({ onClose, onCreated }) {
  const { user } = useAuth();
  const [form, setForm] = useState({ project_id: '', start_date: '', end_date: '', reason: '' });
  const [projects, setProjects] = useState([]);
  const [projectsLoading, setProjectsLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    getEmployeeWorkHistory(user.id, { active: 1 })
      .then((history) => {
        if (cancelled) {
          return;
        }

        // `active=1` filters assignments by end_date IS NULL server-side, which isn't quite
        // the same condition LeaveRequestService validates against (status === 'active') -
        // e.g. a not-yet-started 'pending' assignment also has a null end_date. Filter by
        // the assignment's actual status here so the picker never offers a project the
        // backend would then reject.
        const activeProjects = history.projects.filter((project) => project.status === 'active');
        setProjects(activeProjects);

        if (activeProjects.length === 1) {
          setForm((f) => ({ ...f, project_id: String(activeProjects[0].project_id) }));
        }
      })
      .finally(() => {
        if (!cancelled) {
          setProjectsLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [user.id]);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const created = await createLeaveRequest({
        ...form,
        project_id: form.project_id ? Number(form.project_id) : null,
      });
      toast.success('Gửi yêu cầu nghỉ phép thành công.');
      onCreated?.(created);
      onClose();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" onClick={onClose}>
      <div
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Tạo yêu cầu nghỉ phép</h2>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Đóng">
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          {!projectsLoading && projects.length > 0 && (
            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Dự án</span>
              <select
                required
                value={form.project_id}
                onChange={(e) => setForm({ ...form, project_id: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                <option value="" disabled>
                  Chọn dự án...
                </option>
                {projects.map((project) => (
                  <option key={project.project_id} value={project.project_id}>
                    {project.project}
                  </option>
                ))}
              </select>
            </label>
          )}

          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Từ ngày</span>
            <input
              type="date"
              required
              value={form.start_date}
              onChange={(e) => setForm({ ...form, start_date: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Đến ngày</span>
            <input
              type="date"
              required
              value={form.end_date}
              onChange={(e) => setForm({ ...form, end_date: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <label className="mb-6 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Lý do</span>
            <textarea
              required
              rows={3}
              value={form.reason}
              onChange={(e) => setForm({ ...form, reason: e.target.value })}
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
              disabled={submitting || projectsLoading}
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
