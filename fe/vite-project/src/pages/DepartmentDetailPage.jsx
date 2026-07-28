import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import { getDepartment } from '../api/departments';
import { listEmployees } from '../api/employees';
import Pager from '../components/Pager';

const PAGE_SIZE = 10;

export default function DepartmentDetailPage() {
  const { slug } = useParams();
  const [department, setDepartment] = useState(null);
  const [employees, setEmployees] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  function fetchEmployees(cursor) {
    setLoading(true);

    listEmployees({
      department_slug: slug,
      name: search || undefined,
      per_page: PAGE_SIZE,
      cursor: cursor ?? undefined,
    })
      .then((res) => {
        setEmployees(res.data);
        setMeta(res.meta);
      })
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setDepartment(null);
    setSearch('');

    getDepartment(slug).then(setDepartment);
  }, [slug]);

  useEffect(() => {
    const timeout = setTimeout(() => fetchEmployees(null), 300);

    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, search]);

  function handleNext() {
    fetchEmployees(meta.next_cursor);
  }

  function handlePrev() {
    fetchEmployees(meta.prev_cursor);
  }

  function stubAction(message) {
    return () => toast.info(message);
  }

  if (!department) {
    return <p className="text-gray-500">Đang tải...</p>;
  }

  return (
    <div>
      <Link to="/departments" className="mb-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Quay lại danh sách phòng ban
      </Link>

      <div className="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 className="text-xl font-semibold text-gray-900">{department.name}</h1>
        <p className="mt-1 text-sm text-gray-500">{department.description || 'Không có mô tả.'}</p>
        <span className="mt-3 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
          {department.status}
        </span>
      </div>

      <h2 className="mb-3 text-lg font-medium text-gray-900">Nhân viên</h2>

      <input
        type="text"
        placeholder="Tìm kiếm theo tên..."
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="mb-4 w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm"
      />

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Tên</th>
              <th className="px-4 py-2 font-medium">Email</th>
              <th className="px-4 py-2 font-medium">Vai trò</th>
              <th className="px-4 py-2 font-medium">Hành động</th>
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && employees.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500">
                  Không có nhân viên nào.
                </td>
              </tr>
            )}

            {!loading &&
              employees.map((employee) => (
                <tr key={employee.id} className="border-b border-gray-100 last:border-0">
                  <td className="px-4 py-2">{employee.name}</td>
                  <td className="px-4 py-2 text-gray-500">{employee.email}</td>
                  <td className="px-4 py-2">{employee.position}</td>
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
                        onClick={stubAction('Chức năng xóa đang được phát triển.')}
                        className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                      >
                        Xóa
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
    </div>
  );
}
