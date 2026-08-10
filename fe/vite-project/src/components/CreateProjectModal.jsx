import { useState } from 'react';
import { toast } from 'sonner';
import { createProject } from '../api/projects';
import { addMonthsIso, DURATION_PRESETS, todayIso } from '../lib/duration-presets';
import EmployeeMultiSelect from './EmployeeMultiSelect';

export default function CreateProjectModal({ onClose, onCreated }) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  // Defaults to today per business rule - a project always has a start date,
  // it just doesn't need an end date up front (that can be set later).
  const [startDate, setStartDate] = useState(todayIso());
  const [endDate, setEndDate] = useState('');
  const [managers, setManagers] = useState([]);
  const [submitting, setSubmitting] = useState(false);

  // Quick-fill helper: anchors off the current start date (or today, if it
  // was cleared) and sets the end date that many months out.
  function applyDurationPreset(months) {
    const anchor = startDate || todayIso();
    setStartDate(anchor);
    setEndDate(addMonthsIso(anchor, months));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const created = await createProject({
        name,
        description: description || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        manager_employee_ids: managers.map((manager) => manager.id),
      });
      toast.success('Tạo dự án thành công.');
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
          <h2 className="text-lg font-semibold text-gray-900">Thêm dự án</h2>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            aria-label="Đóng"
          >
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Tên dự án</span>
            <input
              type="text"
              required
              maxLength={255}
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Mô tả</span>
            <textarea
              rows={3}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <div className="mb-4 flex gap-4">
            <label className="block flex-1">
              <span className="mb-1 block text-sm font-medium text-gray-700">Ngày bắt đầu</span>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>

            <label className="block flex-1">
              <span className="mb-1 block text-sm font-medium text-gray-700">Ngày kết thúc (không bắt buộc)</span>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              />
            </label>
          </div>

          <div className="mb-4 flex flex-wrap items-center gap-2">
            <span className="text-xs text-gray-500">Thời lượng nhanh:</span>
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
            <span className="mb-1 block text-sm font-medium text-gray-700">
              Project Manager (chọn ít nhất 1)
            </span>
            <EmployeeMultiSelect selected={managers} onChange={setManagers} />
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
              disabled={submitting || managers.length === 0}
              className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
            >
              {submitting ? 'Đang tạo...' : 'Tạo dự án'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
