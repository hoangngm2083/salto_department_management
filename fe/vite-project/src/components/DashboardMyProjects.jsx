import { Link } from 'react-router-dom';
import { getEmployeeWorkHistory } from '../api/employees';
import useAsyncResource from '../hooks/useAsyncResource';
import { PROJECT_STATUS_OPTIONS } from '../lib/project-status';
import DashboardWidgetCard from './DashboardWidgetCard';

const PROJECT_STATUS_LABELS = Object.fromEntries(PROJECT_STATUS_OPTIONS.map((option) => [option.value, option.label]));

/**
 * "Dự án đang tham gia": active (not-yet-ended) project assignments, from
 * work-history. Only ever shown to an employee (mục 9.2), who can't reach
 * `/projects` (admin/manager only) - title links to their own profile page
 * instead, which already renders this same history as a full Gantt timeline
 * (`WorkHistoryPanel`).
 */
export default function DashboardMyProjects({ employeeId }) {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () => getEmployeeWorkHistory(employeeId).then((history) => history.projects.filter((p) => !p.end_date)),
    deps: [employeeId],
  });

  const projects = data ?? [];

  const title = (
    <Link to={`/employees/${employeeId}`} className="hover:underline">
      Dự án đang tham gia
    </Link>
  );

  return (
    <DashboardWidgetCard
      title={title}
      loading={loading}
      refreshing={refreshing}
      error={error}
      onRefresh={refresh}
      empty={!loading && projects.length === 0}
      emptyText="Chưa tham gia dự án nào."
    >
      <ul className="divide-y divide-gray-100">
        {projects.map((project, index) => {
          const activeRoles = project.roles.filter((role) => !role.end_date).map((role) => role.role);

          return (
            <li key={index} className="py-2 text-sm">
              <div className="flex items-center gap-2">
                <Link to={`/projects/${project.project_slug}`} className="font-medium text-gray-900 hover:underline">
                  {project.project}
                </Link>
                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                  {PROJECT_STATUS_LABELS[project.project_status] ?? project.project_status}
                </span>
              </div>
              <p className="mt-1 text-xs text-gray-500">
                {activeRoles.join(', ') || '—'}
                {project.project_end_date && ` · đến ${project.project_end_date}`}
              </p>
            </li>
          );
        })}
      </ul>
    </DashboardWidgetCard>
  );
}
