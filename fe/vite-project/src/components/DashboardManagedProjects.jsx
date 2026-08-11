import { Link } from 'react-router-dom';
import { getEmployeeManagedProjects } from '../api/employees';
import { useAuth } from '../context/useAuth';
import useAsyncResource from '../hooks/useAsyncResource';
import DashboardWidgetCard from './DashboardWidgetCard';

/**
 * "Dự án tôi quản lý": projects the viewer is currently an active PM of, with
 * progress. Shared between the employee and manager dashboards - PM-ness
 * isn't tied to system role (mục 9.1), so either can have data here. Renders
 * nothing at all when there's none, instead of an empty card, since most
 * employees (and plenty of managers) aren't a PM of anything.
 *
 * Title links to the filtered project list - but `/projects` is admin/manager
 * only (route-gated and `ProjectPolicy::viewAny`), so an employee viewer
 * falls back to their own profile page instead of a 403.
 */
export default function DashboardManagedProjects({ employeeId }) {
  const { user } = useAuth();
  const { data, loading, error, refreshing, refresh } = useAsyncResource({
    fetcher: () => getEmployeeManagedProjects(employeeId),
    deps: [employeeId],
  });

  if (loading || error || !data || data.length === 0) {
    return null;
  }

  const titleTo =
    user.position === 'employee' ? `/employees/${employeeId}` : `/projects?manager_employee_id=${employeeId}`;

  const title = (
    <Link to={titleTo} className="hover:underline">
      Dự án tôi quản lý
    </Link>
  );

  return (
    <DashboardWidgetCard title={title} loading={false} refreshing={refreshing} error={false} onRefresh={refresh}>
      <ul className="divide-y divide-gray-100">
        {data.map((project) => {
          const total = project.total_count ?? 0;
          const done = project.done_count ?? 0;
          // Stacked bar instead of a single done/total ratio - shows where the
          // work is actually sitting right now (mục 7.2), same status -> hue
          // mapping as the Kanban board's badges, one shade more saturated so
          // it reads against the track background.
          const segments = [
            { key: 'todo', count: project.todo_count ?? 0, className: 'bg-gray-300' },
            { key: 'in_progress', count: project.in_progress_count ?? 0, className: 'bg-orange-400' },
            { key: 'in_review', count: project.in_review_count ?? 0, className: 'bg-yellow-400' },
            { key: 'done', count: done, className: 'bg-green-500' },
          ];

          return (
            <li key={project.id} className="py-2 text-sm">
              <div className="flex items-center justify-between gap-2">
                <Link to={`/projects/${project.slug}`} className="font-medium text-gray-900 hover:underline">
                  {project.name}
                </Link>
                {project.overdue_count > 0 && (
                  <span className="shrink-0 text-xs font-medium text-red-600">{project.overdue_count} quá hạn</span>
                )}
              </div>
              <div className="mt-1.5 flex items-center gap-2">
                <div className="flex h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                  {total > 0 &&
                    segments.map(
                      (segment) =>
                        segment.count > 0 && (
                          <div
                            key={segment.key}
                            className={segment.className}
                            style={{ width: `${(segment.count / total) * 100}%` }}
                          />
                        )
                    )}
                </div>
                <span className="shrink-0 text-xs text-gray-500">
                  {done}/{total}
                </span>
              </div>
            </li>
          );
        })}
      </ul>
    </DashboardWidgetCard>
  );
}
