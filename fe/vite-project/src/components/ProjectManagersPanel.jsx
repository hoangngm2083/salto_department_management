import { useState } from 'react';
import { toast } from 'sonner';
import { addProjectManager, removeProjectManager } from '../api/projects';
import EmployeeSearchSelect from './EmployeeSearchSelect';

/**
 * PM management for a project's detail page - not a flat CRUD list. Removing
 * the last active manager requires picking a replacement first (mirrors the
 * 422 the backend would otherwise return for `replacement_employee_id`).
 */
export default function ProjectManagersPanel({ project, canManage, onChanged }) {
  const activeManagers = project.managers.filter((manager) => !manager.end_date);

  const [showAdd, setShowAdd] = useState(false);
  const [newManager, setNewManager] = useState(null);
  const [adding, setAdding] = useState(false);

  const [replacementForId, setReplacementForId] = useState(null);
  const [replacement, setReplacement] = useState(null);
  const [removingId, setRemovingId] = useState(null);

  function cancelAdd() {
    setShowAdd(false);
    setNewManager(null);
  }

  async function handleAdd() {
    if (!newManager) {
      return;
    }

    setAdding(true);

    try {
      await addProjectManager(project.slug, newManager.id);
      toast.success('Thêm project manager thành công.');
      cancelAdd();
      onChanged();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setAdding(false);
    }
  }

  function startRemove(manager) {
    if (activeManagers.length <= 1) {
      setReplacementForId(manager.id);
      setReplacement(null);

      return;
    }

    if (!window.confirm(`Gỡ "${manager.employee_name}" khỏi vai trò PM của dự án này?`)) {
      return;
    }

    doRemove(manager.id, null);
  }

  function cancelReplacement() {
    setReplacementForId(null);
    setReplacement(null);
  }

  async function confirmRemoveWithReplacement(manager) {
    if (!replacement) {
      return;
    }

    await doRemove(manager.id, replacement.id);
    cancelReplacement();
  }

  async function doRemove(projectManagerId, replacementEmployeeId) {
    setRemovingId(projectManagerId);

    try {
      await removeProjectManager(project.slug, projectManagerId, replacementEmployeeId);
      toast.success('Cập nhật project manager thành công.');
      onChanged();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setRemovingId(null);
    }
  }

  return (
    <div className="h-full rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-medium text-gray-900">Project Manager</h2>
        {canManage && !showAdd && (
          <button
            type="button"
            onClick={() => setShowAdd(true)}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Thêm PM
          </button>
        )}
      </div>

      {canManage && showAdd && (
        <div className="mb-4 flex items-start gap-2">
          <div className="flex-1">
            <EmployeeSearchSelect
              value={newManager?.id}
              valueLabel={newManager?.name}
              onChange={(id, name) => setNewManager(id ? { id, name } : null)}
            />
          </div>
          <button
            type="button"
            onClick={handleAdd}
            disabled={!newManager || adding}
            className="rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
          >
            {adding ? 'Đang thêm...' : 'Thêm'}
          </button>
          <button
            type="button"
            onClick={cancelAdd}
            disabled={adding}
            className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
          >
            Hủy
          </button>
        </div>
      )}

      <ul className="divide-y divide-gray-100">
        {activeManagers.length === 0 && (
          <li className="py-2 text-sm text-gray-500">Chưa có project manager nào.</li>
        )}

        {activeManagers.map((manager) => (
          <li key={manager.id} className="py-2">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-900">{manager.employee_name}</p>
                <p className="text-xs text-gray-500">Từ {manager.start_date}</p>
              </div>

              {canManage && replacementForId !== manager.id && (
                <button
                  type="button"
                  onClick={() => startRemove(manager)}
                  disabled={removingId === manager.id}
                  className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                >
                  {removingId === manager.id ? 'Đang gỡ...' : 'Gỡ'}
                </button>
              )}
            </div>

            {canManage && replacementForId === manager.id && (
              <div className="mt-2 rounded-md bg-amber-50 p-3">
                <p className="mb-2 text-xs text-amber-800">
                  Đây là PM active cuối cùng của dự án. Chọn PM thay thế trước khi gỡ.
                </p>
                <div className="flex items-start gap-2">
                  <div className="flex-1">
                    <EmployeeSearchSelect
                      value={replacement?.id}
                      valueLabel={replacement?.name}
                      excludeId={manager.employee_id}
                      onChange={(id, name) => setReplacement(id ? { id, name } : null)}
                      placeholder="Chọn PM thay thế..."
                    />
                  </div>
                  <button
                    type="button"
                    onClick={() => confirmRemoveWithReplacement(manager)}
                    disabled={!replacement || removingId === manager.id}
                    className="rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
                  >
                    {removingId === manager.id ? 'Đang gỡ...' : 'Xác nhận gỡ'}
                  </button>
                  <button
                    type="button"
                    onClick={cancelReplacement}
                    disabled={removingId === manager.id}
                    className="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                  >
                    Hủy
                  </button>
                </div>
              </div>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
