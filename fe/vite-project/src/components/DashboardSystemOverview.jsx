import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { listDepartments } from '../api/departments';
import { countEmployees } from '../api/employees';
import useAsyncResource from '../hooks/useAsyncResource';
import { PROJECT_STATUS_OPTIONS } from '../lib/project-status';
import DashboardWidgetCard from './DashboardWidgetCard';

// Cursor pagination never returns a total (mục 7: "no OFFSET"), so a count
// past this page size is shown as "N+" instead of pretending it's exact -
// same acceptable-for-demo-scope cap as TasksPanel's BOARD_PAGE_SIZE.
const PAGE_SIZE = 100;

function formatCount(page) {
  return page.meta.next_cursor ? `${page.data.length}+` : String(page.data.length);
}

/**
 * "Tổng quan hệ thống": admin-only counts across the whole app, clickable
 * into the filtered list. `projectsResource` is the "all projects with
 * counts" fetch shared with `DashboardAtRiskProjects` (lifted to
 * `DashboardPage` so both widgets don't each hit `GET /projects`).
 */
export default function DashboardSystemOverview({ projectsResource }) {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () =>
      Promise.all([listDepartments({ status: 'active', per_page: PAGE_SIZE }), countEmployees()]),
  });

  const stats = useMemo(() => {
    if (!data || !projectsResource.data) {
      return null;
    }

    const [departments, employeeCount] = data;

    return {
      departmentsActive: formatCount(departments),
      employees: String(employeeCount.total),
      projectsByStatus: PROJECT_STATUS_OPTIONS.map((option) => ({
        ...option,
        count: projectsResource.data.filter((project) => project.status === option.value).length,
      })),
    };
  }, [data, projectsResource.data]);

  const isLoading = loading || projectsResource.loading;
  const hasError = error || projectsResource.error;

  function refreshAll() {
    refresh();
    projectsResource.refresh();
  }

  return (
    <DashboardWidgetCard
      title="Tổng quan hệ thống"
      loading={isLoading}
      refreshing={refreshing || projectsResource.refreshing}
      error={hasError}
      onRefresh={refreshAll}
    >
      {stats && (
        <>
          <div className="mb-3 grid grid-cols-2 gap-2">
            <Link to="/departments" className="rounded-md bg-gray-50 p-2 text-center transition-colors hover:bg-gray-100">
              <p className="text-lg font-semibold text-gray-900">{stats.departmentsActive}</p>
              <p className="text-xs text-gray-500">Phòng ban đang hoạt động</p>
            </Link>
            <Link to="/employees" className="rounded-md bg-gray-50 p-2 text-center transition-colors hover:bg-gray-100">
              <p className="text-lg font-semibold text-gray-900">{stats.employees}</p>
              <p className="text-xs text-gray-500">Nhân viên</p>
            </Link>
          </div>

          <p className="mb-1.5 text-xs font-semibold tracking-wide text-gray-400 uppercase">Dự án theo trạng thái</p>
          <div className="grid grid-cols-4 gap-2">
            {stats.projectsByStatus.map((option) => (
              <Link
                key={option.value}
                to={`/projects?status=${option.value}`}
                className="rounded-md bg-gray-50 p-2 text-center transition-colors hover:bg-gray-100"
              >
                <p className="text-lg font-semibold text-gray-900">{option.count}</p>
                <p className="text-xs text-gray-500">{option.label}</p>
              </Link>
            ))}
          </div>
        </>
      )}
    </DashboardWidgetCard>
  );
}
