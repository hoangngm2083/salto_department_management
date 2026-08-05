import { useState } from 'react';
import { toast } from 'sonner';
import { createLevel } from '../api/levels';

export default function CreateLevelModal({ onClose, onCreated }) {
  const [name, setName] = useState('');
  const [rank, setRank] = useState('');
  const [probationSalaryPercentage, setProbationSalaryPercentage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const created = await createLevel({
        name,
        rank: Number(rank),
        probation_salary_percentage: probationSalaryPercentage
          ? Number(probationSalaryPercentage)
          : undefined,
      });
      toast.success('Tạo cấp bậc thành công.');
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
          <h2 className="text-lg font-semibold text-gray-900">Thêm cấp bậc</h2>
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
            <span className="mb-1 block text-sm font-medium text-gray-700">Tên cấp bậc</span>
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
            <span className="mb-1 block text-sm font-medium text-gray-700">
              Rank (thứ tự cấp bậc, số nhỏ hơn xếp trước)
            </span>
            <input
              type="number"
              required
              min={0}
              max={65535}
              value={rank}
              onChange={(e) => setRank(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
          </label>

          <label className="mb-6 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">
              % lương thử việc
            </span>
            <input
              type="number"
              min={0}
              max={100}
              placeholder="Để trống nếu không áp dụng"
              value={probationSalaryPercentage}
              onChange={(e) => setProbationSalaryPercentage(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
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
              {submitting ? 'Đang tạo...' : 'Tạo cấp bậc'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
