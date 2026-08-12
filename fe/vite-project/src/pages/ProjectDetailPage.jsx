import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import { getProject, updateProject } from '../api/projects';
import AssignmentsPanel from '../components/AssignmentsPanel';
import BackLink from '../components/BackLink';
import ProjectManagersPanel from '../components/ProjectManagersPanel';
import TasksPanel from '../components/TasksPanel';
import { useAuth } from '../context/useAuth';
import { PROJECT_STATUS_OPTIONS } from '../lib/project-status';
import { roleHomePath } from '../lib/role-redirect';

export default function ProjectDetailPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [project, setProject] = useState(null);
  const [loadError, setLoadError] = useState(false);

  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);
  const [saving, setSaving] = useState(false);

  // ProjectPolicy::update/manageManagers are admin-only unconditionally (no
  // "own project" exception like departments have for their manager).
  const canManage = user.position === 'admin';
  // manageAssignments, unlike manageManagers, is also granted to the
  // project's own active managers (see plan mục 7.6). project is still null
  // on the first render, before load() resolves.
  const canManageAssignments =
    canManage ||
    (project?.managers ?? []).some((manager) => !manager.end_date && manager.employee_id === user.id);

  function load() {
    getProject(slug)
      .then(setProject)
      .catch((err) => {
        const status = err.response?.status;

        if (status === 403) {
          navigate('/403', { replace: true });
        } else if (status === 404) {
          navigate('/404', { replace: true });
        } else {
          setLoadError(true);
        }
      });
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setProject(null);
    setLoadError(false);
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug]);

  function startEditing() {
    setDraft({
      name: project.name,
      description: project.description ?? '',
      status: project.status,
      start_date: project.start_date ?? '',
      end_date: project.end_date ?? '',
    });
    setEditing(true);
  }

  function cancelEditing() {
    setDraft(null);
    setEditing(false);
  }

  async function handleSave() {
    setSaving(true);

    try {
      const updated = await updateProject(slug, {
        name: draft.name,
        description: draft.description || null,
        status: draft.status,
        start_date: draft.start_date || null,
        end_date: draft.end_date || null,
      });
      setEditing(false);
      setDraft(null);

      if (updated.slug !== slug) {
        toast.success('Cập nhật dự án thành công. Đã cập nhật URL mới.');
        navigate(`/projects/${updated.slug}`, { replace: true });
      } else {
        toast.success('Cập nhật dự án thành công.');
        setProject(updated);
      }
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSaving(false);
    }
  }

  if (loadError) {
    return <p className="text-red-600">Không thể tải thông tin dự án.</p>;
  }

  return (
    <div>
      <BackLink
        fallback={user.position === 'admin' || user.position === 'manager' ? '/projects' : roleHomePath(user)}
        className="mb-4 inline-block text-sm text-gray-600 hover:underline"
      >
        &larr; Trang trước
      </BackLink>

      <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-10">
        <div className="h-full rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-7">
          {!project && <p className="text-gray-500">Đang tải...</p>}

          {project && (editing ? (
            <div className="space-y-4">
              <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">Tên dự án</span>
                <input
                  type="text"
                  required
                  maxLength={255}
                  value={draft.name}
                  onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
              </label>

              <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">Mô tả</span>
                <textarea
                  rows={3}
                  value={draft.description}
                  onChange={(e) => setDraft({ ...draft, description: e.target.value })}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
              </label>

              <label className="block">
                <span className="mb-1 block text-sm font-medium text-gray-700">Trạng thái</span>
                <select
                  value={draft.status}
                  onChange={(e) => setDraft({ ...draft, status: e.target.value })}
                  className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                >
                  {PROJECT_STATUS_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </label>

              <div className="flex gap-4">
                <label className="block flex-1">
                  <span className="mb-1 block text-sm font-medium text-gray-700">Ngày bắt đầu</span>
                  <input
                    type="date"
                    value={draft.start_date}
                    onChange={(e) => setDraft({ ...draft, start_date: e.target.value })}
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  />
                </label>

                <label className="block flex-1">
                  <span className="mb-1 block text-sm font-medium text-gray-700">Ngày kết thúc</span>
                  <input
                    type="date"
                    value={draft.end_date}
                    onChange={(e) => setDraft({ ...draft, end_date: e.target.value })}
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  />
                </label>
              </div>

              <div className="flex justify-end gap-2">
                <button
                  type="button"
                  onClick={cancelEditing}
                  disabled={saving}
                  className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                >
                  Hủy
                </button>
                <button
                  type="button"
                  onClick={handleSave}
                  disabled={saving}
                  className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
                >
                  {saving ? 'Đang lưu...' : 'Lưu'}
                </button>
              </div>
            </div>
          ) : (
            <>
              <div className="flex items-start justify-between">
                <h1 className="text-xl font-semibold text-gray-900">{project.name}</h1>
                {canManage && (
                  <button
                    type="button"
                    onClick={startEditing}
                    className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                  >
                    Cập nhật
                  </button>
                )}
              </div>
              <p className="mt-1 text-sm text-gray-500">{project.description || 'Không có mô tả.'}</p>
              <div className="mt-3 flex items-center gap-3">
                <span className="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                  {project.status}
                </span>
                {(project.start_date || project.end_date) && (
                  <span className="text-xs text-gray-500">
                    {project.start_date ?? '?'} &rarr; {project.end_date ?? 'hiện tại'}
                  </span>
                )}
              </div>
              {project.total_count !== undefined && (
                <p className="mt-2 text-xs text-gray-500">
                  {project.done_count}/{project.total_count} task hoàn thành
                  {project.overdue_count > 0 && (
                    <span className="font-medium text-red-600"> · {project.overdue_count} quá hạn</span>
                  )}
                </p>
              )}
            </>
          ))}
        </div>

        <div className="lg:col-span-3">
          {project ? (
            <ProjectManagersPanel project={project} canManage={canManage} onChanged={load} />
          ) : (
            <div className="h-full rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
              <p className="text-sm text-gray-500">Đang tải...</p>
            </div>
          )}
        </div>
      </div>

      {/* slug is known synchronously from useParams(), so these two fetch in
          parallel with getProject() above instead of waiting for it to resolve
          first - project is passed through only for the optional fields each
          panel already guards with `project?.` (timeline bounds, canManage). */}
      <AssignmentsPanel slug={slug} project={project} canManage={canManageAssignments} />

      <TasksPanel slug={slug} canManage={canManageAssignments} onChanged={load} />
    </div>
  );
}
