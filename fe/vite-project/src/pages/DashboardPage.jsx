import { listProjects } from '../api/projects';
import { useAuth } from '../context/useAuth';
import useAsyncResource from '../hooks/useAsyncResource';
import DashboardAtRiskProjects from '../components/DashboardAtRiskProjects';
import DashboardDepartmentStats from '../components/DashboardDepartmentStats';
import DashboardManagedProjects from '../components/DashboardManagedProjects';
import DashboardMyProjects from '../components/DashboardMyProjects';
import DashboardOverdueManagedTasks from '../components/DashboardOverdueManagedTasks';
import DashboardPendingRequests from '../components/DashboardPendingRequests';
import DashboardSystemOverview from '../components/DashboardSystemOverview';
import DashboardTaskSummary from '../components/DashboardTaskSummary';

const PROJECTS_PAGE_SIZE = 100;

/**
 * New post-login landing page for every role (plan mục 9.1), replacing the
 * old per-role redirect to an existing list/profile page. Widget set per
 * role is mục 9.2's starting point - each widget fetches independently
 * (`useAsyncResource`), so this page itself holds no data, only layout.
 *
 * Exception: `SystemOverview` and `AtRiskProjects` both need "all projects
 * with task-progress counts" (admin-only), so that one fetch is lifted here
 * and shared instead of each widget hitting `GET /projects` on its own.
 */
export default function DashboardPage() {
  const { user } = useAuth();
  const isAdmin = user.position === 'admin';

  const projectsResource = useAsyncResource({
    fetcher: () =>
      isAdmin
        ? listProjects({ per_page: PROJECTS_PAGE_SIZE, with_counts: 1 }).then((res) => res.data)
        : Promise.resolve([]),
  });

  return (
    <div>
      <h1 className="mb-6 text-xl font-semibold text-gray-900">Trang chủ</h1>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {isAdmin && (
          <>
            <DashboardSystemOverview projectsResource={projectsResource} />
            <DashboardAtRiskProjects projectsResource={projectsResource} />
            <DashboardPendingRequests title="Yêu cầu đang chờ xử lý" />
          </>
        )}

        {user.position === 'manager' && (
          <>
            <DashboardDepartmentStats />
            <DashboardPendingRequests title="Yêu cầu đang chờ duyệt" />
          </>
        )}

        {user.position === 'employee' && (
          <>
            <DashboardTaskSummary employeeId={user.id} />
            <DashboardMyProjects employeeId={user.id} />
            <DashboardPendingRequests title="Yêu cầu của tôi đang chờ duyệt" />
          </>
        )}

        {user.is_project_manager && (
          <>
            <DashboardManagedProjects employeeId={user.id} />
            <DashboardOverdueManagedTasks employeeId={user.id} />
          </>
        )}
      </div>
    </div>
  );
}
