import { useAuth } from '../context/useAuth';
import DashboardAtRiskProjects from '../components/DashboardAtRiskProjects';
import DashboardDepartmentStats from '../components/DashboardDepartmentStats';
import DashboardManagedProjects from '../components/DashboardManagedProjects';
import DashboardMyProjects from '../components/DashboardMyProjects';
import DashboardOverdueManagedTasks from '../components/DashboardOverdueManagedTasks';
import DashboardPendingRequests from '../components/DashboardPendingRequests';
import DashboardSystemOverview from '../components/DashboardSystemOverview';
import DashboardTaskSummary from '../components/DashboardTaskSummary';

/**
 * New post-login landing page for every role (plan mục 9.1), replacing the
 * old per-role redirect to an existing list/profile page. Widget set per
 * role is mục 9.2's starting point - each widget fetches independently
 * (`useAsyncResource`), so this page itself holds no data, only layout.
 */
export default function DashboardPage() {
  const { user } = useAuth();

  return (
    <div>
      <h1 className="mb-6 text-xl font-semibold text-gray-900">Trang chủ</h1>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {user.position === 'admin' && (
          <>
            <DashboardSystemOverview />
            <DashboardAtRiskProjects />
            <DashboardPendingRequests title="Yêu cầu đang chờ xử lý" includeTaskDelayRequests />
          </>
        )}

        {user.position === 'manager' && (
          <>
            <DashboardDepartmentStats />
            <DashboardPendingRequests title="Yêu cầu đang chờ duyệt" includeTaskDelayRequests={false} />
          </>
        )}

        {user.position === 'employee' && (
          <>
            <DashboardTaskSummary employeeId={user.id} />
            <DashboardMyProjects employeeId={user.id} />
            <DashboardPendingRequests title="Yêu cầu của tôi đang chờ duyệt" includeTaskDelayRequests />
          </>
        )}

        <DashboardManagedProjects employeeId={user.id} />
        <DashboardOverdueManagedTasks employeeId={user.id} />
      </div>
    </div>
  );
}
