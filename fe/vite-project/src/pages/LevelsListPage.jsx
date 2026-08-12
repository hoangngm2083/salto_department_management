import { useState } from 'react';
import { toast } from 'sonner';
import { deleteLevel, listLevels, updateLevel } from '../api/levels';
import CreateLevelModal from '../components/CreateLevelModal';
import Pager from '../components/Pager';
import { useAuth } from '../context/useAuth';
import { LEVEL_STATUS_OPTIONS } from '../lib/level-status';
import useCursorList from '../hooks/useCursorList';

const STATUS_OPTIONS = [...LEVEL_STATUS_OPTIONS, { value: 'all', label: 'Tất cả' }];

export default function LevelsListPage() {
  const { user } = useAuth();
  const isAdmin = user.position === 'admin';

  const [status, setStatus] = useState('active');
  const [search, setSearch] = useState('');

  const {
    items: levels,
    meta,
    loading,
    refreshing,
    goToNext,
    goToPrev,
    refresh,
    replaceItem,
    removeItem,
  } = useCursorList({
    fetcher: listLevels,
    params: { status },
    belongsInList: (level) => status === 'all' || level.status === status,
  });

  const [editingSlug, setEditingSlug] = useState(null);
  const [draft, setDraft] = useState(null);
  const [saving, setSaving] = useState(false);
  const [deletingSlug, setDeletingSlug] = useState(null);
  const [showCreate, setShowCreate] = useState(false);

  function startEditing(level) {
    setEditingSlug(level.slug);
    setDraft({
      name: level.name,
      rank: level.rank,
      probation_salary_percentage: level.probation_salary_percentage ?? '',
      status: level.status,
    });
  }

  function cancelEditing() {
    setEditingSlug(null);
    setDraft(null);
  }

  async function handleUpdate(level) {
    setSaving(true);

    try {
      const updated = await updateLevel(level.slug, {
        name: draft.name,
        rank: Number(draft.rank),
        probation_salary_percentage:
          draft.probation_salary_percentage === '' ? null : Number(draft.probation_salary_percentage),
        status: draft.status,
      });
      toast.success('Cập nhật cấp bậc thành công.');
      cancelEditing();
      replaceItem(updated);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(level) {
    if (!window.confirm(`Xóa cấp bậc "${level.name}"?`)) {
      return;
    }

    setDeletingSlug(level.slug);

    try {
      await deleteLevel(level.slug);
      toast.success('Xóa cấp bậc thành công.');
      removeItem(level.id);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setDeletingSlug(null);
    }
  }

  const filtered = levels.filter((l) => l.name.toLowerCase().includes(search.trim().toLowerCase()));

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Danh sách cấp bậc</h1>
        {isAdmin && (
          <button
            type="button"
            onClick={() => setShowCreate(true)}
            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
          >
            Thêm cấp bậc
          </button>
        )}
      </div>

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
              <th className="px-4 py-2 font-medium">Rank</th>
              <th className="px-4 py-2 font-medium">% lương thử việc</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
              {isAdmin && <th className="px-4 py-2 font-medium">Hành động</th>}
            </tr>
          </thead>
          <tbody
            className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
          >
            {loading && (
              <tr>
                <td colSpan={isAdmin ? 5 : 4} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && filtered.length === 0 && (
              <tr>
                <td colSpan={isAdmin ? 5 : 4} className="px-4 py-6 text-center text-gray-500">
                  Không có cấp bậc nào.
                </td>
              </tr>
            )}

            {!loading &&
              filtered.map((level) => {
                const isEditing = editingSlug === level.slug;
                const isDeleting = deletingSlug === level.slug;

                return (
                  <tr key={level.id} className="border-b border-gray-100 last:border-0">
                    <td className="px-4 py-2">
                      {isEditing ? (
                        <input
                          type="text"
                          value={draft.name}
                          onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                          className="w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
                        />
                      ) : (
                        level.name
                      )}
                    </td>
                    <td className="px-4 py-2 text-gray-500">
                      {isEditing ? (
                        <input
                          type="number"
                          min={0}
                          max={65535}
                          value={draft.rank}
                          onChange={(e) => setDraft({ ...draft, rank: e.target.value })}
                          className="w-24 rounded-md border border-gray-300 px-2 py-1 text-sm"
                        />
                      ) : (
                        level.rank
                      )}
                    </td>
                    <td className="px-4 py-2 text-gray-500">
                      {isEditing ? (
                        <input
                          type="number"
                          min={0}
                          max={100}
                          value={draft.probation_salary_percentage}
                          onChange={(e) =>
                            setDraft({ ...draft, probation_salary_percentage: e.target.value })
                          }
                          className="w-24 rounded-md border border-gray-300 px-2 py-1 text-sm"
                        />
                      ) : (
                        (level.probation_salary_percentage ?? '—')
                      )}
                    </td>
                    <td className="px-4 py-2">
                      {isEditing ? (
                        <select
                          value={draft.status}
                          onChange={(e) => setDraft({ ...draft, status: e.target.value })}
                          className="rounded-md border border-gray-300 px-2 py-1 text-sm"
                        >
                          {LEVEL_STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                          {level.status}
                        </span>
                      )}
                    </td>
                    {isAdmin && (
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
                              onClick={() => handleUpdate(level)}
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
                              onClick={() => startEditing(level)}
                              className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                            >
                              Cập nhật
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDelete(level)}
                              disabled={isDeleting}
                              className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                            >
                              {isDeleting ? 'Đang xóa...' : 'Xóa'}
                            </button>
                          </div>
                        )}
                      </td>
                    )}
                  </tr>
                );
              })}
          </tbody>
        </table>
      </div>

      <Pager
        hasPrev={Boolean(meta.prev_cursor)}
        hasNext={Boolean(meta.next_cursor)}
        onPrev={goToPrev}
        onNext={goToNext}
      />

      {showCreate && <CreateLevelModal onClose={() => setShowCreate(false)} onCreated={refresh} />}
    </div>
  );
}
