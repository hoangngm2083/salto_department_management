import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import { listDepartments } from '../api/departments';
import Pager from '../components/Pager';

const STATUS_OPTIONS = [
  { value: 'active', label: 'Đang hoạt động' },
  { value: 'inactive', label: 'Ngừng hoạt động' },
  { value: 'all', label: 'Tất cả' },
];

export default function DepartmentsListPage() {
  const [status, setStatus] = useState('active');
  const [search, setSearch] = useState('');
  const [departments, setDepartments] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);

  function fetchPage(cursor) {
    setLoading(true);

    listDepartments({ status, per_page: 15, cursor: cursor ?? undefined })
      .then((res) => {
        setDepartments(res.data);
        setMeta(res.meta);
      })
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    fetchPage(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  function handleNext() {
    fetchPage(meta.next_cursor);
  }

  function handlePrev() {
    fetchPage(meta.prev_cursor);
  }

  function stubAction(message) {
    return () => toast.info(message);
  }

  const filtered = departments.filter((d) =>
    d.name.toLowerCase().includes(search.trim().toLowerCase())
  );

  return (
    <div>
      <h1 className="mb-6 text-xl font-semibold text-gray-900">Danh sách phòng ban</h1>

      <div className="mb-4 flex gap-3">
        <input
          type="text"
          placeholder="Tìm kiếm theo tên..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        />
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          {STATUS_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Tên</th>
              <th className="px-4 py-2 font-medium">Slug</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
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

            {!loading && filtered.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500">
                  Không có phòng ban nào.
                </td>
              </tr>
            )}

            {!loading &&
              filtered.map((department) => (
                <tr key={department.id} className="border-b border-gray-100 last:border-0">
                  <td className="px-4 py-2">{department.name}</td>
                  <td className="px-4 py-2 text-gray-500">{department.slug}</td>
                  <td className="px-4 py-2">
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                      {department.status}
                    </span>
                  </td>
                  <td className="px-4 py-2">
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={stubAction('Chức năng cập nhật đang được phát triển.')}
                        className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                      >
                        Cập nhật
                      </button>
                      <button
                        type="button"
                        onClick={stubAction('Chức năng xóa đang được phát triển.')}
                        className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                      >
                        Xóa
                      </button>
                      <Link
                        to={`/departments/${department.slug}`}
                        className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                      >
                        Xem chi tiết
                      </Link>
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
