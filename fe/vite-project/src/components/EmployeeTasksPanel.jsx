import { useState } from 'react';
import { getEmployeeTasks } from '../api/employees';
import useCursorList from '../hooks/useCursorList';
import { CANCELLED_TITLE_CLASS, TASK_STATUS_BADGE_CLASSES, TASK_STATUS_LABELS } from '../lib/task-status';
import Pager from './Pager';
import TaskDetailModal from './TaskDetailModal';

/**
 * Read-only "assigned tasks" list on an employee's profile - mirrors
 * `WorkHistoryPanel`'s position on `EmployeeProfilePage`. Opens
 * `TaskDetailModal` without `canReview` (no project context here), so only
 * the assignee's own self-service transitions show; PM/admin review still
 * happens from the task's own project board.
 */
export default function EmployeeTasksPanel({ employeeId }) {
  const [openTask, setOpenTask] = useState(null);

  const {
    items: tasks,
    meta,
    loading,
    refreshing,
    goToNext,
    goToPrev,
    replaceItem,
  } = useCursorList({
    fetcher: (params) => getEmployeeTasks(employeeId, params),
    params: {},
  });

  const today = new Date().toISOString().slice(0, 10);

  function handleUpdated(updated) {
    replaceItem(updated);
    setOpenTask(updated);
  }

  return (
    <div className="h-full rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <h2 className="mb-4 text-lg font-medium text-gray-900">Task được giao</h2>

      {loading && <p className="text-sm text-gray-500">Đang tải...</p>}

      {!loading && tasks.length === 0 && <p className="text-sm text-gray-500">Chưa có task nào.</p>}

      {!loading && tasks.length > 0 && (
        <ul
          className={`divide-y divide-gray-100 transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
        >
          {tasks.map((task) => {
            const isOverdue = task.due_date && task.due_date < today && !['done', 'cancelled'].includes(task.status);

            return (
              <li key={task.id}>
                <button
                  type="button"
                  onClick={() => setOpenTask(task)}
                  className="flex w-full items-center justify-between gap-2 py-2 text-left text-sm hover:bg-gray-50"
                >
                  <div>
                    <p
                      className={`font-medium text-gray-900 ${task.status === 'cancelled' ? CANCELLED_TITLE_CLASS : ''}`}
                    >
                      {task.title}
                    </p>
                    {task.project_name && <p className="text-xs text-gray-500">{task.project_name}</p>}
                  </div>
                  <div className="flex shrink-0 flex-col items-end gap-1">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${TASK_STATUS_BADGE_CLASSES[task.status] ?? 'bg-gray-100 text-gray-700'}`}
                    >
                      {TASK_STATUS_LABELS[task.status] ?? task.status}
                    </span>
                    {task.due_date && (
                      <span className={`text-xs ${isOverdue ? 'font-medium text-red-600' : 'text-gray-400'}`}>
                        {task.due_date}
                      </span>
                    )}
                  </div>
                </button>
              </li>
            );
          })}
        </ul>
      )}

      <Pager hasPrev={Boolean(meta.prev_cursor)} hasNext={Boolean(meta.next_cursor)} onPrev={goToPrev} onNext={goToNext} />

      {openTask && <TaskDetailModal task={openTask} onClose={() => setOpenTask(null)} onUpdated={handleUpdated} />}
    </div>
  );
}
