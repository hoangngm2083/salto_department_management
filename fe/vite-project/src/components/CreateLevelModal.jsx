import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { createLevel, listLevels } from '../api/levels';

const NO_REFERENCE_POSITIONS = new Set(['start', 'end']);

/**
 * Rank is never typed in directly - the admin picks where the new level
 * slots into the existing order (start/end of the ladder, or right before/
 * after another level) and the backend derives a gap-based rank from that
 * (`LevelService::resolveRankForInsert`).
 */
export default function CreateLevelModal({ onClose, onCreated }) {
  const [name, setName] = useState('');
  const [probationSalaryPercentage, setProbationSalaryPercentage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [existingLevels, setExistingLevels] = useState([]);
  const [loadingLevels, setLoadingLevels] = useState(true);
  const [insertPosition, setInsertPosition] = useState('end');
  const [referenceLevelId, setReferenceLevelId] = useState('');

  useEffect(() => {
    listLevels({ status: 'all', per_page: 100 })
      .then((res) => setExistingLevels([...res.data].sort((a, b) => a.rank - b.rank)))
      .catch(() => {})
      .finally(() => setLoadingLevels(false));
  }, []);

  const needsReference = !NO_REFERENCE_POSITIONS.has(insertPosition);
  const canSubmit = name.trim() !== '' && (!needsReference || referenceLevelId !== '');

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const created = await createLevel({
        name,
        insert_position: insertPosition,
        reference_level_id: needsReference ? Number(referenceLevelId) : undefined,
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

          {!loadingLevels && existingLevels.length > 0 && (
            <div className="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
              <span className="mb-1 block text-xs font-medium text-gray-500">
                Thứ tự cấp bậc hiện có (thấp &rarr; cao)
              </span>
              <ol className="list-inside list-decimal text-sm text-gray-700">
                {existingLevels.map((level) => (
                  <li key={level.id}>{level.name}</li>
                ))}
              </ol>
            </div>
          )}

          <label className="mb-4 block">
            <span className="mb-1 block text-sm font-medium text-gray-700">Vị trí trong danh sách</span>
            <select
              value={insertPosition}
              onChange={(e) => {
                setInsertPosition(e.target.value);
                setReferenceLevelId('');
              }}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
              <option value="start">Đầu danh sách (thấp nhất)</option>
              <option value="end">Cuối danh sách (cao nhất)</option>
              <option value="before">Trước một cấp bậc...</option>
              <option value="after">Sau một cấp bậc...</option>
            </select>
          </label>

          {needsReference && (
            <label className="mb-4 block">
              <span className="mb-1 block text-sm font-medium text-gray-700">
                {insertPosition === 'before' ? 'Trước cấp bậc' : 'Sau cấp bậc'}
              </span>
              <select
                required
                value={referenceLevelId}
                onChange={(e) => setReferenceLevelId(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              >
                <option value="">-- Chọn cấp bậc --</option>
                {existingLevels.map((level) => (
                  <option key={level.id} value={level.id}>
                    {level.name}
                  </option>
                ))}
              </select>
            </label>
          )}

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
              disabled={submitting || !canSubmit}
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
