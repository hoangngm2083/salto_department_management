import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import { deleteDepartment, listDepartments, updateDepartment } from '../api/departments';
import Pager from '../components/Pager';

const STATUS_OPTIONS = [
  { value: 'active', label: 'Đang hoạt động' },
  { value: 'inactive', label: 'Ngừng hoạt động' },
  { value: 'all', label: 'Tất cả' },
];

const EDITABLE_STATUS_OPTIONS = STATUS_OPTIONS.filter((opt) => opt.value !== 'all');

export default function DepartmentsListPage() {
  const [status, setStatus] = useState('active');
  const [search, setSearch] = useState('');
  const [departments, setDepartments] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [cursor, setCursor] = useState(null);

  const [editingSlug, setEditingSlug] = useState(null);
  const [draft, setDraft] = useState(null);
  const [saving, setSaving] = useState(false);
  const [deletingSlug, setDeletingSlug] = useState(null);

  function fetchPage(nextCursor) {
    setLoading(true);
    setCursor(nextCursor ?? null);

    listDepartments({ status, per_page: 15, cursor: nextCursor ?? undefined })
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

  function startEditing(department) {
    setEditingSlug(department.slug);
    setDraft({ name: department.name, status: department.status });
  }

  function cancelEditing() {
    setEditingSlug(null);
    setDraft(null);
  }

  async function handleUpdate(department) {
    setSaving(true);

    try {
      await updateDepartment(department.slug, draft);
      toast.success('Cập nhật phòng ban thành công.');
      cancelEditing();
      fetchPage(cursor);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(department) {
    if (!window.confirm(`Xóa phòng ban "${department.name}"?`)) {
      return;
    }

    setDeletingSlug(department.slug);

    try {
      await deleteDepartment(department.slug);
      toast.success('Xóa phòng ban thành công.');
      fetchPage(cursor);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setDeletingSlug(null);
    }
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
              filtered.map((department) => {
                const isEditing = editingSlug === department.slug;
                const isDeleting = deletingSlug === department.slug;

                return (
                  <tr key={department.id} className="border-b border-gray-100 last:border-0">
                    <td className="px-4 py-2">
                      {isEditing ? (
                        <input
                          type="text"
                          value={draft.name}
                          onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                          className="w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
                        />
                      ) : (
                        department.name
                      )}
                    </td>
                    <td className="px-4 py-2 text-gray-500">{department.slug}</td>
                    <td className="px-4 py-2">
                      {isEditing ? (
                        <select
                          value={draft.status}
                          onChange={(e) => setDraft({ ...draft, status: e.target.value })}
                          className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                        >
                          {EDITABLE_STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                          {department.status}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      {isEditing ? (
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={cancelEditing}
                            disabled={saving}
                            className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                          >
                            Hủy
                          </button>
                          <button
                            type="button"
                            onClick={() => handleUpdate(department)}
                            disabled={saving}
                            className="rounded-md bg-gray-900 px-2 py-1 text-xs font-medium text-white disabled:opacity-50 hover:bg-gray-700"
                          >
                            {saving ? 'Đang lưu...' : 'Lưu'}
                          </button>
                        </div>
                      ) : (
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={() => startEditing(department)}
                            className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                          >
                            Cập nhật
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(department)}
                            disabled={isDeleting}
                            className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                          >
                            {isDeleting ? 'Đang xóa...' : 'Xóa'}
                          </button>
                          <Link
                            to={`/departments/${department.slug}`}
                            className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                          >
                            Xem chi tiết
                          </Link>
                        </div>
                      )}
                    </td>
                  </tr>
                );
              })}
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
