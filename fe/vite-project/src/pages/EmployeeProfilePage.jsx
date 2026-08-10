import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { getEmployee } from '../api/employees';
import { useAuth } from '../context/useAuth';
import BackLink from '../components/BackLink';
import EmployeeProfileView from '../components/EmployeeProfileView';
import EmployeeTasksPanel from '../components/EmployeeTasksPanel';
import WorkHistoryPanel from '../components/WorkHistoryPanel';
import { roleHomePath } from '../lib/role-redirect';

export default function EmployeeProfilePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [employee, setEmployee] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setLoading(true);
    setError(false);

    getEmployee(id)
      .then(setEmployee)
      .catch((err) => {
        const status = err.response?.status;

        if (status === 403) {
          navigate('/403', { replace: true });
        } else if (status === 404) {
          navigate('/404', { replace: true });
        } else {
          setError(true);
        }
      })
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (loading) {
    return <p className="text-gray-500">Đang tải...</p>;
  }

  if (error || !employee) {
    return <p className="text-red-600">Không thể tải thông tin nhân viên.</p>;
  }

  const canEdit =
    user.position === 'admin' ||
    employee.id === user.id ||
    (user.position === 'manager' && employee.department_slug === user.department_slug);

  // Only admin/manager can reach /departments/:slug at all - a plain employee
  // (self-view only) has nowhere for this link to actually lead.
  const canViewDepartment = user.position === 'admin' || user.position === 'manager';

  return (
    <div>
      {canViewDepartment && (
        <BackLink
          fallback={roleHomePath(user)}
          className="mb-4 inline-block text-sm text-gray-600 hover:underline"
        >
          &larr; Trang trước
        </BackLink>
      )}
      <EmployeeProfileView employee={employee} canEdit={canEdit} onSaved={setEmployee} />
      <WorkHistoryPanel employeeId={employee.id} />
      <EmployeeTasksPanel employeeId={employee.id} />
    </div>
  );
}
