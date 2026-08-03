import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { listDepartments } from '../api/departments';
import { createEmployee } from '../api/employees';
import { useAuth } from '../context/useAuth';
import { POSITION_OPTIONS, ROLE_LABELS } from '../lib/role-labels';

export default function CreateEmployeeModal({ onClose, onCreated }) {
  const { user: actor } = useAuth();
  const isAdmin = actor.position === 'admin';
  const blocked = actor.position === 'manager' && !actor.department_id;

  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    birthday: '',
    department_id: '',
    position: 'employee',
  });
  const [departments, setDepartments] = useState([]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (isAdmin) {
      listDepartments({ status: 'all', per_page: 100 })
        .then((res) => setDepartments(res.data))
        .catch(() => {});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    const payload = {
      name: form.name,
      email: form.email,
      password: form.password,
      birthday: form.birthday,
      department_id: isAdmin ? Number(form.department_id) : actor.department_id,
      position: isAdmin ? form.position : 'employee',
    };

    try {
      const created = await createEmployee(payload);
      toast.success('Thêm nhân viên thành công.');
      onCreated?.(created);
      onClose();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
      onClick={onClose}
    >
      <div
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Thêm nhân viên</h2>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            aria-label="Đóng"
          >
            &times;
          </button>
        </div>

        {blocked ? (
          <>
            <p className="text-sm text-red-600">
              Tài khoản của bạn chưa được gán phòng ban, không thể tạo nhân viên.
            </p>
            <div className="mt-6 flex justify-end">
              <button
                type="button"
                onClick={onClose}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
              >
                Đóng
              </button>
            </div>
          </>
        ) : (
          <form onSubmit={handleSubmit}>
            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Họ tên</span>
              <input
                type="text"
                required
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Email</span>
              <input
                type="email"
                required
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Mật khẩu</span>
              <input
                type="password"
                required
                minLength={8}
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">Ngày sinh</span>
              <input
                type="date"
                required
                value={form.birthday}
                onChange={(e) => setForm({ ...form, birthday: e.target.value })}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            {isAdmin ? (
              <>
                <label className="mb-4 block">
                  <span className="mb-1 block text-sm font-medium text-gray-700">Phòng ban</span>
                  <select
                    required
                    value={form.department_id}
                    onChange={(e) => setForm({ ...form, department_id: e.target.value })}
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  >
                    <option value="" disabled>
                      Chọn phòng ban
                    </option>
                    {departments.map((department) => (
                      <option key={department.id} value={department.id}>
                        {department.name}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="mb-6 block">
                  <span className="mb-1 block text-sm font-medium text-gray-700">Vai trò</span>
                  <select
                    value={form.position}
                    onChange={(e) => setForm({ ...form, position: e.target.value })}
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                  >
                    {POSITION_OPTIONS.map((position) => (
                      <option key={position} value={position}>
                        {ROLE_LABELS[position] ?? position}
                      </option>
                    ))}
                  </select>
                </label>
              </>
            ) : (
              <p className="mb-6 text-xs text-gray-500">
                Nhân viên mới sẽ được thêm vào phòng ban của bạn với vai trò Nhân viên.
              </p>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={onClose}
                disabled={submitting}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
              >
                Hủy
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
              >
                {submitting ? 'Đang tạo...' : 'Thêm nhân viên'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
