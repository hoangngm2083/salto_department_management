import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import { listDepartments } from '../api/departments';
import { deleteEmployee, listEmployees } from '../api/employees';
import { exportEmployees } from '../api/exports';
import { useAuth } from '../context/useAuth';
import { ROLE_LABELS } from '../lib/role-labels';
import useCursorList from '../hooks/useCursorList';
import CreateEmployeeModal from '../components/CreateEmployeeModal';
import ImportEmployeesModal from '../components/ImportEmployeesModal';
import Pager from '../components/Pager';

export default function EmployeesListPage() {
  const { user } = useAuth();
  const isAdmin = user.position === 'admin';

  const [search, setSearch] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [departments, setDepartments] = useState([]);
  const [deletingId, setDeletingId] = useState(null);
  const [showImport, setShowImport] = useState(false);
  const [showCreate, setShowCreate] = useState(false);
  const [exporting, setExporting] = useState(false);

  const { items: employees, meta, loading, refreshing, goToNext, goToPrev, refresh, removeItem } =
    useCursorList({
      fetcher: listEmployees,
      params: {
        name: search || undefined,
        department_id: departmentId || undefined,
      },
      debounceMs: 300,
    });

  useEffect(() => {
    // A manager can't call GET /departments at all (admin-only on BE) - skip the
    // fetch entirely rather than firing a doomed request for a filter they'd
    // never see anyway.
    if (isAdmin) {
      listDepartments({ status: 'all', per_page: 100 }).then((res) => setDepartments(res.data));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleDelete(employee) {
    if (!window.confirm(`Xóa nhân viên "${employee.name}"?`)) {
      return;
    }

    setDeletingId(employee.id);

    try {
      await deleteEmployee(employee.id);
      toast.success('Xóa nhân viên thành công.');
      removeItem(employee.id);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setDeletingId(null);
    }
  }

  async function handleExport() {
    setExporting(true);

    try {
      await exportEmployees({ name: search || undefined, department_id: departmentId || undefined });
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setExporting(false);
    }
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Danh sách nhân viên</h1>
        <div className="flex gap-2">
          {isAdmin && (
            <>
              <button
                type="button"
                onClick={handleExport}
                disabled={exporting}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
              >
                {exporting ? 'Đang xuất...' : 'Xuất CSV'}
              </button>
              <button
                type="button"
                onClick={() => setShowImport(true)}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
              >
                Nhập từ CSV
              </button>
            </>
          )}
          <button
            type="button"
            onClick={() => setShowCreate(true)}
            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
          >
            Thêm nhân viên
          </button>
        </div>
      </div>

      <div className="mb-4 flex gap-3">
        <input
          type="text"
          placeholder="Tìm kiếm theo tên..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        />
        {isAdmin && (
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
        )}
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Tên</th>
              <th className="px-4 py-2 font-medium">Email</th>
              <th className="px-4 py-2 font-medium">Phòng ban</th>
              <th className="px-4 py-2 font-medium">Vai trò</th>
              {isAdmin && <th className="px-4 py-2 font-medium">Hành động</th>}
            </tr>
          </thead>
          <tbody
            className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
          >
            {loading && (
              <tr>
                <td colSpan={isAdmin ? 5 : 4} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && employees.length === 0 && (
              <tr>
                <td colSpan={isAdmin ? 5 : 4} className="px-4 py-6 text-center text-gray-500">
                  Không có nhân viên nào.
                </td>
              </tr>
            )}

            {!loading &&
              employees.map((employee) => (
                <tr key={employee.id} className="border-b border-gray-100 last:border-0">
                  <td className="px-4 py-2">
                    <Link to={`/employees/${employee.id}`} className="text-gray-900 hover:underline">
                      {employee.name}
                    </Link>
                  </td>
                  <td className="px-4 py-2 text-gray-500">{employee.email}</td>
                  <td className="px-4 py-2 text-gray-500">{employee.department_name}</td>
                  <td className="px-4 py-2">
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                      {ROLE_LABELS[employee.position] ?? employee.position}
                    </span>
                  </td>
                  {isAdmin && (
                    <td className="px-4 py-2">
                      <button
                        type="button"
                        onClick={() => handleDelete(employee)}
                        disabled={deletingId === employee.id}
                        className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                      >
                        {deletingId === employee.id ? 'Đang xóa...' : 'Xóa'}
                      </button>
                    </td>
                  )}
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Pager
        hasPrev={Boolean(meta.prev_cursor)}
        hasNext={Boolean(meta.next_cursor)}
        onPrev={goToPrev}
        onNext={goToNext}
      />

      {showImport && (
        <ImportEmployeesModal onClose={() => setShowImport(false)} onImported={refresh} />
      )}

      {showCreate && (
        <CreateEmployeeModal onClose={() => setShowCreate(false)} onCreated={refresh} />
      )}
    </div>
  );
}
