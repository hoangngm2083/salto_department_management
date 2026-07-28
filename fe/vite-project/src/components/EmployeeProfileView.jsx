import { useState } from 'react';
import { toast } from 'sonner';
import { ROLE_LABELS } from '../lib/role-labels';

export default function EmployeeProfileView({ employee, canEdit }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);

  function startEditing() {
    setDraft({
      name: employee.name,
      email: employee.email,
      birthday: employee.birthday ?? '',
    });
    setEditing(true);
  }

  function cancelEditing() {
    setDraft(null);
    setEditing(false);
  }

  function handleSave() {
    toast.info('Chức năng cập nhật đang được phát triển.');
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

        <Field label="Vai trò">
          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
            {ROLE_LABELS[employee.position] ?? employee.position}
          </span>
        </Field>

        <Field label="Phòng ban">
          <span>{employee.department_name ?? '—'}</span>
        </Field>
      </dl>

      {editing && (
        <div className="mt-6 flex justify-end gap-2">
          <button
            type="button"
            onClick={cancelEditing}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Hủy
          </button>
          <button
            type="button"
            onClick={handleSave}
            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
          >
            Lưu
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
