import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { getEmployeeTasks } from '../api/employees';
import TaskDetailModal from '../components/TaskDetailModal';
import Pager from '../components/Pager';
import { useAuth } from '../context/useAuth';
import useCursorList from '../hooks/useCursorList';
import { CANCELLED_TITLE_CLASS, TASK_STATUS_BADGE_CLASSES, TASK_STATUS_LABELS } from '../lib/task-status';

const SORT_OPTIONS = [
  { value: 'due_date', label: 'Hạn chót' },
  { value: 'created_at', label: 'Ngày tạo' },
];

/**
 * "Task của tôi" - flat, filterable list of every task assigned to the
 * current employee across every project, linked from the dashboard's task
 * status tiles (mục 3). Filter/sort state seeds from and syncs back to the
 * URL (`useSearchParams`) so a dashboard link like `/my-tasks?status=todo`
 * lands pre-filtered - the first page in the app to read query params.
 * "Chỉ hiện quá hạn" is a client-side filter only: tasks don't have an
 * "overdue" status, it's derived from `due_date`, same as everywhere else
 * in the app (EmployeeTasksPanel, TasksPanel).
 */
export default function MyTasksPage() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const [status, setStatus] = useState(() => searchParams.get('status') ?? '');
  const [sort, setSort] = useState(() => searchParams.get('sort') ?? 'due_date');
  const [direction, setDirection] = useState(() => searchParams.get('direction') ?? 'asc');
  const [overdueOnly, setOverdueOnly] = useState(() => searchParams.get('overdue') === '1');
  const [openTask, setOpenTask] = useState(null);

  function updateFilters(next) {
    const merged = { status, sort, direction, overdueOnly, ...next };

    setStatus(merged.status);
    setSort(merged.sort);
    setDirection(merged.direction);
    setOverdueOnly(merged.overdueOnly);

    const params = {};

    if (merged.status) {
      params.status = merged.status;
    }

    params.sort = merged.sort;
    params.direction = merged.direction;

    if (merged.overdueOnly) {
      params.overdue = '1';
    }

    setSearchParams(params);
  }

  const {
    items: tasks,
    meta,
    loading,
    refreshing,
    goToNext,
    goToPrev,
    replaceItem,
  } = useCursorList({
    fetcher: (params) => getEmployeeTasks(user.id, params),
    params: { status: status || undefined, sort, direction },
  });

  const today = new Date().toISOString().slice(0, 10);

  const visibleTasks = useMemo(() => {
    if (!overdueOnly) {
      return tasks;
    }

    return tasks.filter((task) => task.due_date && task.due_date < today && !['done', 'cancelled'].includes(task.status));
  }, [tasks, overdueOnly, today]);

  function handleUpdated(updated) {
    replaceItem(updated);
    setOpenTask(updated);
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Task của tôi</h1>
      </div>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <select
          value={status}
          onChange={(e) => updateFilters({ status: e.target.value })}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          <option value="">Tất cả trạng thái</option>
          {Object.entries(TASK_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>

        <select
          value={sort}
          onChange={(e) => updateFilters({ sort: e.target.value })}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          {SORT_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              Sắp xếp: {option.label}
            </option>
          ))}
        </select>

        <button
          type="button"
          onClick={() => updateFilters({ direction: direction === 'asc' ? 'desc' : 'asc' })}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
        >
          {direction === 'asc' ? 'Tăng dần ↑' : 'Giảm dần ↓'}
        </button>

        <label className="flex items-center gap-1.5 text-sm text-gray-700">
          <input
            type="checkbox"
            checked={overdueOnly}
            onChange={(e) => updateFilters({ overdueOnly: e.target.checked })}
            className="rounded border-gray-300"
          />
          Chỉ hiện quá hạn
        </label>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Task</th>
              <th className="px-4 py-2 font-medium">Dự án</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
              <th className="px-4 py-2 font-medium">Hạn chót</th>
              <th className="px-4 py-2 font-medium">Ngày tạo</th>
            </tr>
          </thead>
          <tbody
            className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
          >
            {loading && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && visibleTasks.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                  Không có task nào.
                </td>
              </tr>
            )}

            {!loading &&
              visibleTasks.map((task) => {
                const isOverdue = task.due_date && task.due_date < today && !['done', 'cancelled'].includes(task.status);

                return (
                  <tr
                    key={task.id}
                    onClick={() => setOpenTask(task)}
                    className="cursor-pointer border-b border-gray-100 last:border-0 hover:bg-gray-50"
                  >
                    <td
                      className={`px-4 py-2 font-medium text-gray-900 ${task.status === 'cancelled' ? CANCELLED_TITLE_CLASS : ''}`}
                    >
                      {task.title}
                    </td>
                    <td className="px-4 py-2 text-gray-500">{task.project_name ?? '—'}</td>
                    <td className="px-4 py-2">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${TASK_STATUS_BADGE_CLASSES[task.status] ?? 'bg-gray-100 text-gray-700'}`}
                      >
                        {TASK_STATUS_LABELS[task.status] ?? task.status}
                      </span>
                    </td>
                    <td className={`px-4 py-2 ${isOverdue ? 'font-medium text-red-600' : 'text-gray-500'}`}>
                      {task.due_date ?? '—'}
                      {isOverdue && ' (quá hạn)'}
                    </td>
                    <td className="px-4 py-2 text-gray-500">{task.created_at?.slice(0, 10) ?? '—'}</td>
                  </tr>
                );
              })}
          </tbody>
        </table>
      </div>

      <Pager hasPrev={Boolean(meta.prev_cursor)} hasNext={Boolean(meta.next_cursor)} onPrev={goToPrev} onNext={goToNext} />

      {openTask && <TaskDetailModal task={openTask} onClose={() => setOpenTask(null)} onUpdated={handleUpdated} />}
    </div>
  );
}
