import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';
import { getDepartment, updateDepartment } from '../api/departments';
import { deleteEmployee, listEmployees } from '../api/employees';
import { useAuth } from '../context/useAuth';
import BackLink from '../components/BackLink';
import Pager from '../components/Pager';
import { DEPARTMENT_STATUS_OPTIONS } from '../lib/department-status';

const PAGE_SIZE = 10;

export default function DepartmentDetailPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { user, updateUser } = useAuth();
  const [department, setDepartment] = useState(null);
  const [loadError, setLoadError] = useState(false);
  const [employees, setEmployees] = useState([]);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [cursor, setCursor] = useState(null);
  const [deletingId, setDeletingId] = useState(null);

  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);
  const [saving, setSaving] = useState(false);

  const isAdmin = user.position === 'admin';
  const canEdit = user.position === 'admin' || user.position === 'manager';
  const canDelete = user.position === 'admin';

  function fetchEmployees(nextCursor) {
    setLoading(true);
    setCursor(nextCursor ?? null);

    listEmployees({
      department_slug: slug,
      name: search || undefined,
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
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting view state before an external fetch, per React's documented data-fetching pattern
    setDepartment(null);
    setLoadError(false);
    setSearch('');

    getDepartment(slug)
      .then(setDepartment)
      .catch((err) => {
        const status = err.response?.status;

        if (status === 403) {
          navigate('/403', { replace: true });
        } else if (status === 404) {
          navigate('/404', { replace: true });
        } else {
          setLoadError(true);
        }
      });
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

  async function handleDelete(employee) {
    if (!window.confirm(`Xóa nhân viên "${employee.name}"?`)) {
      return;
    }

    setDeletingId(employee.id);

    try {
      await deleteEmployee(employee.id);
      toast.success('Xóa nhân viên thành công.');
      fetchEmployees(cursor);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setDeletingId(null);
    }
  }

  function startEditing() {
    setDraft({
      name: department.name,
      description: department.description ?? '',
      status: department.status,
    });
    setEditing(true);
  }

  function cancelEditing() {
    setDraft(null);
    setEditing(false);
  }

  async function handleSave() {
    setSaving(true);

    try {
      const updated = await updateDepartment(slug, draft);
      setEditing(false);
      setDraft(null);

      // The manager's own department_slug (used by the header nav and
      // post-login redirect) would otherwise stay stale until next reload.
      if (user.position === 'manager' && user.department_id === updated.id) {
        updateUser({ ...user, department_slug: updated.slug });
      }

      if (updated.slug !== slug) {
        toast.success('Cập nhật phòng ban thành công. Đã cập nhật URL mới.');
        navigate(`/departments/${updated.slug}`, { replace: true });
      } else {
        toast.success('Cập nhật phòng ban thành công.');
        setDepartment(updated);
      }
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSaving(false);
    }
  }

  if (loadError) {
    return <p className="text-red-600">Không thể tải thông tin phòng ban.</p>;
  }

  if (!department) {
    return <p className="text-gray-500">Đang tải...</p>;
  }

  return (
    <div>
      {isAdmin && (
        <BackLink fallback="/departments" className="mb-4 inline-block text-sm text-gray-600 hover:underline">
          &larr; Trang trước
        </BackLink>
      )}

      <div className="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        {editing ? (
          <div className="space-y-4">
            <label className="block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Tên phòng ban</span>
              <input
                type="text"
                required
                maxLength={255}
                value={draft.name}
                onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Mô tả</span>
              <textarea
                rows={3}
                value={draft.description}
                onChange={(e) => setDraft({ ...draft, description: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Trạng thái</span>
              <select
                value={draft.status}
                onChange={(e) => setDraft({ ...draft, status: e.target.value })}
                className="rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                {DEPARTMENT_STATUS_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </label>

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={cancelEditing}
                disabled={saving}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={handleSave}
                disabled={saving}
                className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
              >
                {saving ? 'Đang lưu...' : 'Lưu'}
              </button>
            </div>
          </div>
        ) : (
          <>
            <div className="flex items-start justify-between">
              <h1 className="text-xl font-semibold text-gray-900">{department.name}</h1>
              {canEdit && (
                <button
                  type="button"
                  onClick={startEditing}
                  className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                  Cập nhật
                </button>
              )}
            </div>
            <p className="mt-1 text-sm text-gray-500">{department.description || 'Không có mô tả.'}</p>
            <span className="mt-3 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
              {department.status}
            </span>
          </>
        )}
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
                      {canDelete && (
                        <button
                          type="button"
                          onClick={() => handleDelete(employee)}
                          disabled={deletingId === employee.id}
                          className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                        >
                          {deletingId === employee.id ? 'Đang xóa...' : 'Xóa'}
                        </button>
                      )}
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
