import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getEmployee } from '../api/employees';
import { useAuth } from '../context/useAuth';
import EmployeeProfileView from '../components/EmployeeProfileView';

export default function EmployeeProfilePage() {
  const { id } = useParams();
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
      .catch(() => setError(true))
      .finally(() => setLoading(false));
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

  return (
    <div>
      <Link to={`/departments/${employee.department_slug}`} className="mb-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Quay lại phòng ban
      </Link>
      <EmployeeProfileView employee={employee} canEdit={canEdit} />
    </div>
  );
}
