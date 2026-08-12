import { Link } from 'react-router-dom';
import { listEmployees } from '../api/employees';
import useAsyncResource from '../hooks/useAsyncResource';
import { EMPLOYEE_STATUS_OPTIONS } from '../lib/employee-status';
import DashboardWidgetCard from './DashboardWidgetCard';

// Demo/portfolio scope cap, same precedent as TasksPanel's BOARD_PAGE_SIZE.
const PAGE_SIZE = 100;

/**
 * "Nhân viên phòng ban": employee counts by status, manager's own department.
 * No department filter is passed - GetEmployeesRequest::prepareForValidation()
 * already forces department_id to the manager actor's own department, so
 * this call is inherently self-scoped without the widget having to know it.
 */
export default function DashboardDepartmentStats() {
  const { data, loading, refreshing, error, refresh } = useAsyncResource({
    fetcher: () => listEmployees({ per_page: PAGE_SIZE }).then((res) => res.data),
  });

  const employees = data ?? [];

  const counts = EMPLOYEE_STATUS_OPTIONS.map((option) => ({
    ...option,
    count: employees.filter((employee) => employee.status === option.value).length,
  }));

  return (
    <DashboardWidgetCard
      title="Nhân viên phòng ban"
      loading={loading}
      refreshing={refreshing}
      error={error}
      onRefresh={refresh}
      empty={!loading && employees.length === 0}
      emptyText="Chưa có nhân viên nào."
    >
      <div className="grid grid-cols-3 gap-2">
        {counts.map((option) => (
          <Link
            key={option.value}
            to={`/employees?status=${option.value}`}
            className="rounded-md bg-gray-50 p-2 text-center transition-colors hover:bg-gray-100"
          >
            <p className="text-lg font-semibold text-gray-900">{option.count}</p>
            <p className="text-xs text-gray-500">{option.label}</p>
          </Link>
        ))}
      </div>
    </DashboardWidgetCard>
  );
}
