import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { getEmployeeTasks } from '../api/employees';
import useAsyncResource from '../hooks/useAsyncResource';
import { TASK_STATUS_BADGE_CLASSES, TASK_STATUS_LABELS } from '../lib/task-status';
import DashboardWidgetCard from './DashboardWidgetCard';
import TaskDetailModal from './TaskDetailModal';

const SUMMARY_STATUSES = ['todo', 'in_progress', 'in_review'];
// Not cursor-paginated on purpose (mirrors TasksPanel's BOARD_PAGE_SIZE): a
// dashboard summary counts/groups client-side from one fetch, acceptable for
// demo/portfolio scope.
const PAGE_SIZE = 100;

/** "Task của tôi": status counts (+ overdue) + the soonest-due/overdue tasks, employee's own. */
export default function DashboardTaskSummary({ employeeId }) {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () => getEmployeeTasks(employeeId, { per_page: PAGE_SIZE }).then((res) => res.data),
    deps: [employeeId],
  });

  const [openTask, setOpenTask] = useState(null);

  const tasks = useMemo(() => data ?? [], [data]);
  const today = new Date().toISOString().slice(0, 10);

  const isOverdue = (task) => task.due_date && task.due_date < today && !['done', 'cancelled'].includes(task.status);

  const counts = useMemo(() => {
    const result = Object.fromEntries(SUMMARY_STATUSES.map((status) => [status, 0]));

    tasks.forEach((task) => {
      if (result[task.status] !== undefined) {
        result[task.status] += 1;
      }
    });

    return { ...result, overdue: tasks.filter(isOverdue).length };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tasks, today]);

  const tiles = [
    ...SUMMARY_STATUSES.map((status) => ({
      key: status,
      count: counts[status],
      label: TASK_STATUS_LABELS[status],
      to: `/my-tasks?status=${status}`,
      valueClassName: 'text-gray-900',
    })),
    { key: 'overdue', count: counts.overdue, label: 'Quá hạn', to: '/my-tasks?overdue=1', valueClassName: 'text-red-600' },
  ];

  const upcoming = useMemo(
    () =>
      tasks
        .filter((task) => task.due_date && !['done', 'cancelled'].includes(task.status))
        .sort((a, b) => a.due_date.localeCompare(b.due_date))
        .slice(0, 5),
    [tasks]
  );

  function handleUpdated(updated) {
    setOpenTask(updated);
    refresh();
  }

  return (
    <DashboardWidgetCard
      title="Task của tôi"
      loading={loading}
      refreshing={refreshing}
      error={error}
      onRefresh={refresh}
      empty={!loading && tasks.length === 0}
      emptyText="Chưa có task nào."
    >
      <div className="mb-4 grid grid-cols-4 gap-2">
        {tiles.map((tile) => (
          <Link
            key={tile.key}
            to={tile.to}
            className="rounded-md bg-gray-50 p-2 text-center transition-colors hover:bg-gray-100"
          >
            <p className={`text-lg font-semibold ${tile.valueClassName}`}>{tile.count}</p>
            <p className="text-xs text-gray-500">{tile.label}</p>
          </Link>
        ))}
      </div>

      {upcoming.length > 0 && (
        <ul className="divide-y divide-gray-100">
          {upcoming.map((task) => {
            const overdue = isOverdue(task);

            return (
              <li key={task.id}>
                <button
                  type="button"
                  onClick={() => setOpenTask(task)}
                  className="flex w-full items-center justify-between gap-2 py-2 text-left text-sm hover:bg-gray-50"
                >
                  <div className="min-w-0">
                    <p className="truncate font-medium text-gray-900">{task.title}</p>
                    {task.project_name && <p className="truncate text-xs text-gray-500">{task.project_name}</p>}
                  </div>
                  <div className="flex shrink-0 flex-col items-end gap-1">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${TASK_STATUS_BADGE_CLASSES[task.status] ?? 'bg-gray-100 text-gray-700'}`}
                    >
                      {TASK_STATUS_LABELS[task.status] ?? task.status}
                    </span>
                    <span className={`text-xs ${overdue ? 'font-medium text-red-600' : 'text-gray-400'}`}>
                      {task.due_date}
                    </span>
                  </div>
                </button>
              </li>
            );
          })}
        </ul>
      )}

      {openTask && <TaskDetailModal task={openTask} onClose={() => setOpenTask(null)} onUpdated={handleUpdated} />}
    </DashboardWidgetCard>
  );
}
