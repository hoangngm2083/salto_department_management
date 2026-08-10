import { useState } from 'react';
import { toast } from 'sonner';
import { createTask } from '../api/tasks';
import { addMonthsIso, DURATION_PRESETS, todayIso } from '../lib/duration-presets';
import ProjectMemberSearchSelect from './ProjectMemberSearchSelect';

export default function CreateTaskModal({ slug, onClose, onCreated }) {
  const [form, setForm] = useState({ title: '', description: '', due_date: '' });
  const [assignedTo, setAssignedTo] = useState(null);
  const [assignedToLabel, setAssignedToLabel] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  // Quick-fill helper: tasks have no start date, so this just sets the due
  // date that many months out from today.
  function applyDurationPreset(months) {
    setForm((f) => ({ ...f, due_date: addMonthsIso(todayIso(), months) }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const created = await createTask(slug, {
        title: form.title,
        description: form.description || null,
        due_date: form.due_date || null,
        assigned_to: assignedTo,
      });
      toast.success('Tạo task thành công.');
      onCreated?.(created);
      onClose();
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" onClick={onClose}>
      <div
        className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Tạo task</h2>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600" aria-label="Đóng">
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Tiêu đề</span>
            <input
              type="text"
              required
              maxLength={255}
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Mô tả</span>
            <textarea
              rows={3}
              maxLength={2000}
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <label className="mb-2 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Hạn hoàn thành</span>
            <input
              type="date"
              value={form.due_date}
              onChange={(e) => setForm({ ...form, due_date: e.target.value })}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <div className="mb-4 flex flex-wrap items-center gap-2">
            <span className="text-xs text-gray-500">Thời hạn nhanh:</span>
            {DURATION_PRESETS.map((preset) => (
              <button
                key={preset.months}
                type="button"
                onClick={() => applyDurationPreset(preset.months)}
                className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100"
              >
                {preset.label}
              </button>
            ))}
          </div>

          <label className="mb-6 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Giao cho (để trống = chưa giao)</span>
            <ProjectMemberSearchSelect
              slug={slug}
              value={assignedTo}
              valueLabel={assignedToLabel}
              onChange={(id, label) => {
                setAssignedTo(id);
                setAssignedToLabel(label);
              }}
            />
          </label>

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
              {submitting ? 'Đang tạo...' : 'Tạo task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
