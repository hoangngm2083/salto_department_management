import { useState } from 'react';
import { getEmployeeOverdueManagedTasks } from '../api/employees';
import useAsyncResource from '../hooks/useAsyncResource';
import DashboardWidgetCard from './DashboardWidgetCard';
import TaskDetailModal from './TaskDetailModal';

/**
 * "Task quá hạn cần xử lý": overdue tasks (top 10) across every project the
 * viewer currently manages as PM - mục 7.4, so they can see what's trailing
 * without opening each project board individually. Same visibility condition
 * as `DashboardManagedProjects` (PM-ness isn't tied to system role), renders
 * nothing at all when there's none. Row click opens `TaskDetailModal` with
 * `canReview` - the viewer is by construction a PM of every task's project.
 */
export default function DashboardOverdueManagedTasks({ employeeId }) {
  const { data, loading, error, refreshing, refresh } = useAsyncResource({
    fetcher: () => getEmployeeOverdueManagedTasks(employeeId),
    deps: [employeeId],
  });

  const [openTask, setOpenTask] = useState(null);

  if (loading || error || !data || data.length === 0) {
    return null;
  }

  function handleUpdated(updated) {
    setOpenTask(updated);
    refresh();
  }

  return (
    <DashboardWidgetCard
      title="Task quá hạn cần xử lý"
      loading={false}
      refreshing={refreshing}
      error={false}
      onRefresh={refresh}
    >
      <ul className="divide-y divide-gray-100">
        {data.map((task) => (
          <li key={task.id}>
            <button
              type="button"
              onClick={() => setOpenTask(task)}
              className="flex w-full items-center justify-between gap-2 py-2 text-left text-sm hover:bg-gray-50"
            >
              <div className="min-w-0">
                <p className="truncate font-medium text-gray-900">{task.title}</p>
                <p className="truncate text-xs text-gray-500">
                  {task.project_name}
                  {task.assignee_name && ` · ${task.assignee_name}`}
                </p>
              </div>
              <span className="shrink-0 text-xs font-medium text-red-600">{task.due_date}</span>
            </button>
          </li>
        ))}
      </ul>

      {openTask && (
        <TaskDetailModal task={openTask} canReview onClose={() => setOpenTask(null)} onUpdated={handleUpdated} />
      )}
    </DashboardWidgetCard>
  );
}
