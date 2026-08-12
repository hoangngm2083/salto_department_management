import { ArrowPathIcon } from '@heroicons/react/24/outline';

/**
 * Shared shell for a dashboard widget: title + its own "làm mới" button, plus
 * the loading/refreshing/error/empty states every widget needs. Keeps each
 * widget file focused on fetching its own data and rendering its own rows
 * (plan mục 9.1 - mỗi widget tự fetch, tự làm mới độc lập).
 */
export default function DashboardWidgetCard({
  title,
  loading,
  refreshing,
  error,
  onRefresh,
  empty = false,
  emptyText = 'Không có dữ liệu.',
  children,
}) {
  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
        <button
          type="button"
          onClick={onRefresh}
          disabled={loading}
          className="rounded-md p-1 text-gray-400 disabled:opacity-50 hover:bg-gray-100 hover:text-gray-600"
          aria-label="Làm mới"
        >
          <ArrowPathIcon className={`size-4 ${refreshing ? 'animate-spin' : ''}`} />
        </button>
      </div>

      <div className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}>
        {loading && <p className="text-sm text-gray-500">Đang tải...</p>}
        {!loading && error && <p className="text-sm text-red-600">Không thể tải dữ liệu.</p>}
        {!loading && !error && empty && <p className="text-sm text-gray-500">{emptyText}</p>}
        {!loading && !error && !empty && children}
      </div>
    </div>
  );
}
