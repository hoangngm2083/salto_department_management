import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import { listDepartments } from '../api/departments';
import { deleteEmployee, listEmployees } from '../api/employees';
import { ROLE_LABELS } from '../lib/role-labels';
import ImportEmployeesModal from '../components/ImportEmployeesModal';
import Pager from '../components/Pager';

const PAGE_SIZE = 15;

export default function EmployeesListPage() {
  const [search, setSearch] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [departments, setDepartments] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [cursor, setCursor] = useState(null);
  const [deletingId, setDeletingId] = useState(null);
  const [showImport, setShowImport] = useState(false);

  useEffect(() => {
    listDepartments({ status: 'all', per_page: 100 }).then((res) => setDepartments(res.data));
  }, []);

  function fetchEmployees(nextCursor) {
    setLoading(true);
    setCursor(nextCursor ?? null);

    listEmployees({
      name: search || undefined,
      department_id: departmentId || undefined,
      per_page: PAGE_SIZE,
      cursor: nextCursor ?? undefined,
    })
      .then((res) => {
        setEmployees(res.data);
        setMeta(res.meta);
      })
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    const timeout = setTimeout(() => fetchEmployees(null), 300);

    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, departmentId]);

  function handleNext() {
    fetchEmployees(meta.next_cursor);
  }

  function handlePrev() {
    fetchEmployees(meta.prev_cursor);
  }

  async function handleDelete(employee) {
    if (!window.confirm(`Xóa người dùng "${employee.name}"?`)) {
      return;
    }

    setDeletingId(employee.id);

    try {
      await deleteEmployee(employee.id);
      toast.success('Xóa người dùng thành công.');
      fetchEmployees(cursor);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setDeletingId(null);
    }
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Danh sách người dùng</h1>
        <button
          type="button"
          onClick={() => setShowImport(true)}
          className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
        >
          Nhập từ CSV
        </button>
      </div>

      <div className="mb-4 flex gap-3">
        <input
          type="text"
          placeholder="Tìm kiếm theo tên..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        />
        <select
          value={departmentId}
          onChange={(e) => setDepartmentId(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          <option value="">Tất cả phòng ban</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Tên</th>
              <th className="px-4 py-2 font-medium">Email</th>
              <th className="px-4 py-2 font-medium">Phòng ban</th>
              <th className="px-4 py-2 font-medium">Vai trò</th>
              <th className="px-4 py-2 font-medium">Hành động</th>
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && employees.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500">
                  Không có người dùng nào.
                </td>
              </tr>
            )}

            {!loading &&
              employees.map((employee) => (
                <tr key={employee.id} className="border-b border-gray-100 last:border-0">
                  <td className="px-4 py-2">{employee.name}</td>
                  <td className="px-4 py-2 text-gray-500">{employee.email}</td>
                  <td className="px-4 py-2 text-gray-500">{employee.department_name}</td>
                  <td className="px-4 py-2">
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                      {ROLE_LABELS[employee.position] ?? employee.position}
                    </span>
                  </td>
                  <td className="px-4 py-2">
                    <div className="flex gap-2">
                      <Link
                        to={`/employees/${employee.id}`}
                        className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                      >
                        Xem chi tiết
                      </Link>
                      <button
                        type="button"
                        onClick={() => handleDelete(employee)}
                        disabled={deletingId === employee.id}
                        className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                      >
                        {deletingId === employee.id ? 'Đang xóa...' : 'Xóa'}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Pager
        hasPrev={Boolean(meta.prev_cursor)}
        hasNext={Boolean(meta.next_cursor)}
        onPrev={handlePrev}
        onNext={handleNext}
      />

      {showImport && (
        <ImportEmployeesModal
          onClose={() => setShowImport(false)}
          onImported={() => fetchEmployees(cursor)}
        />
      )}
    </div>
  );
}
