import { useEffect, useState } from 'react';
import { listProjectTasks } from '../api/tasks';
import { TASK_STATUS_CARD_CLASSES, TASK_STATUS_COLUMNS, TASK_STATUS_LABELS } from '../lib/task-status';
import CreateTaskModal from './CreateTaskModal';
import TaskDetailModal from './TaskDetailModal';

// Board isn't cursor-paginated (mục 7.3): a Kanban column doesn't fit the
// cursor-list/Pager infra used elsewhere, so this fetches once up to the max
// page size and groups client-side - acceptable for demo/portfolio scope.
const BOARD_PAGE_SIZE = 100;

/**
 * "Task" tab of a project's detail page: a Kanban board grouped by
 * `TaskStatus`, matching mục 7's "dashboard + kanban" note. `canManage`
 * mirrors `ProjectDetailPage`'s `canManageAssignments` (admin or this
 * project's own active PM) - the same condition `TaskPolicy::create` and the
 * PM checks in `TaskPolicy`/`TaskDelayRequestPolicy::update` use, so it
 * doubles as both "may add a task" and the `canReview` passed into the
 * detail modal.
 */
export default function TasksPanel({ project, canManage, onChanged }) {
  const [tasks, setTasks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [openTask, setOpenTask] = useState(null);

  function load() {
    setLoading(true);
    listProjectTasks(project.slug, { per_page: BOARD_PAGE_SIZE })
      .then((res) => setTasks(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [project.slug]);

  // done_count/total_count/overdue_count live on the project resource
  // (mục 5), not recomputed here - re-pulling the whole project after any
  // task mutation keeps that badge accurate, same as ProjectManagersPanel's
  // own onChanged={load}.
  function handleCreated(created) {
    setTasks((prev) => [created, ...prev]);
    onChanged?.();
  }

  function patchTask(updated) {
    setTasks((prev) => prev.map((t) => (t.id === updated.id ? updated : t)));
    setOpenTask(updated);
    onChanged?.();
  }

  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-medium text-gray-900">Task</h2>
        {canManage && (
          <button
            type="button"
            onClick={() => setShowCreate(true)}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            + Thêm task
          </button>
        )}
      </div>

      {loading && <p className="py-2 text-sm text-gray-500">Đang tải...</p>}

      {!loading && tasks.length === 0 && <p className="py-2 text-sm text-gray-500">Chưa có task nào.</p>}

      {!loading && tasks.length > 0 && (
        <div className="grid grid-cols-1 gap-3 overflow-x-auto sm:grid-cols-2 lg:grid-cols-5">
          {TASK_STATUS_COLUMNS.map((status) => {
            const columnTasks = tasks.filter((t) => t.status === status);

            return (
              <div key={status} className="min-w-[200px] rounded-md bg-gray-50 p-2">
                <div className="mb-2 flex items-center justify-between px-1">
                  <span className="text-xs font-semibold tracking-wide text-gray-500 uppercase">
                    {TASK_STATUS_LABELS[status]}
                  </span>
                  <span className="text-xs text-gray-400">{columnTasks.length}</span>
                </div>

                <div className="space-y-2">
                  {columnTasks.map((t) => {
                    const isOverdue = t.due_date && t.due_date < today && !['done', 'cancelled'].includes(t.status);

                    return (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() => setOpenTask(t)}
                        className={`block w-full rounded-md border border-gray-200 p-2 text-left text-xs shadow-sm hover:border-gray-300 ${TASK_STATUS_CARD_CLASSES[status] ?? 'bg-white'}`}
                      >
                        <p className="font-medium text-gray-900">{t.title}</p>
                        {t.assignee_name && <p className="mt-1 text-gray-600">{t.assignee_name}</p>}
                        {t.due_date && (
                          <p className={`mt-1 font-medium ${isOverdue ? 'text-red-600' : 'text-gray-500'}`}>
                            {t.due_date}
                            {isOverdue && ' (quá hạn)'}
                          </p>
                        )}
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {showCreate && (
        <CreateTaskModal slug={project.slug} onClose={() => setShowCreate(false)} onCreated={handleCreated} />
      )}

      {openTask && (
        <TaskDetailModal task={openTask} canReview={canManage} onClose={() => setOpenTask(null)} onUpdated={patchTask} />
      )}
    </div>
  );
}
