import { useState } from 'react';
import { toast } from 'sonner';
import { listDepartments } from '../api/departments';
import { updateEmployee } from '../api/employees';
import { useAuth } from '../context/useAuth';
import { POSITION_OPTIONS, ROLE_LABELS } from '../lib/role-labels';

export default function EmployeeProfileView({ employee, canEdit, onSaved }) {
  const { user: actor, updateUser } = useAuth();
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);
  const [saving, setSaving] = useState(false);
  const [departments, setDepartments] = useState([]);

  // Only admins may reassign department/position, per EmployeeService::ALLOWED_UPDATE_FIELDS_BY_ROLE -
  // managers/employees editing these fields would be silently ignored by the backend.
  const canEditRestrictedFields = canEdit && actor.position === 'admin';

  function startEditing() {
    setDraft({
      name: employee.name,
      email: employee.email,
      birthday: employee.birthday ?? '',
      password: '',
      department_id: employee.department_id ?? '',
      position: employee.position,
    });
    setEditing(true);

    if (canEditRestrictedFields) {
      listDepartments({ status: 'all', per_page: 100 })
        .then((res) => setDepartments(res.data))
        .catch(() => {});
    }
  }

  function cancelEditing() {
    setDraft(null);
    setEditing(false);
  }

  async function handleSave() {
    setSaving(true);

    const payload = {
      name: draft.name,
      email: draft.email,
    };

    if (draft.birthday) {
      payload.birthday = draft.birthday;
    }

    if (draft.password) {
      payload.password = draft.password;
    }

    if (canEditRestrictedFields) {
      payload.department_id = Number(draft.department_id);
      payload.position = draft.position;
    }

    try {
      const updated = await updateEmployee(employee.id, payload);
      toast.success('Cập nhật thông tin nhân viên thành công.');
      setEditing(false);
      setDraft(null);

      if (actor.id === updated.id) {
        updateUser(updated);
      }

      onSaved?.(updated);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mx-auto max-w-xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Thông tin nhân viên</h1>
        {canEdit && !editing && (
          <button
            type="button"
            onClick={startEditing}
            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
          >
            Cập nhật
          </button>
        )}
      </div>

      <dl className="space-y-4">
        <Field label="Họ tên">
          {editing ? (
            <input
              type="text"
              value={draft.name}
              onChange={(e) => setDraft({ ...draft, name: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            />
          ) : (
            <span>{employee.name}</span>
          )}
        </Field>

        <Field label="Email">
          {editing ? (
            <input
              type="email"
              value={draft.email}
              onChange={(e) => setDraft({ ...draft, email: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            />
          ) : (
            <span>{employee.email}</span>
          )}
        </Field>

        <Field label="Ngày sinh">
          {editing ? (
            <input
              type="date"
              value={draft.birthday}
              onChange={(e) => setDraft({ ...draft, birthday: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            />
          ) : (
            <span>{employee.birthday ?? '—'}</span>
          )}
        </Field>

        {editing && (
          <Field label="Mật khẩu mới">
            <input
              type="password"
              value={draft.password}
              onChange={(e) => setDraft({ ...draft, password: e.target.value })}
              placeholder="Để trống nếu không đổi"
              className="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            />
          </Field>
        )}

        <Field label="Vai trò">
          {editing && canEditRestrictedFields ? (
            <select
              value={draft.position}
              onChange={(e) => setDraft({ ...draft, position: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            >
              {POSITION_OPTIONS.map((position) => (
                <option key={position} value={position}>
                  {ROLE_LABELS[position] ?? position}
                </option>
              ))}
            </select>
          ) : (
            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
              {ROLE_LABELS[employee.position] ?? employee.position}
            </span>
          )}
        </Field>

        <Field label="Phòng ban">
          {editing && canEditRestrictedFields ? (
            <select
              value={draft.department_id}
              onChange={(e) => setDraft({ ...draft, department_id: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm"
            >
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name}
                </option>
              ))}
            </select>
          ) : (
            <span>{employee.department_name ?? '—'}</span>
          )}
        </Field>
      </dl>

      {editing && (
        <div className="mt-6 flex justify-end gap-2">
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
      )}
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div className="grid grid-cols-3 items-center gap-4">
      <dt className="text-sm font-medium text-gray-500">{label}</dt>
      <dd className="col-span-2 text-sm text-gray-900">{children}</dd>
    </div>
  );
}
