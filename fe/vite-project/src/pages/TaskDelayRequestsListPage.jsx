import { useState } from 'react';
import { toast } from 'sonner';
import { listTaskDelayRequests, updateTaskDelayRequestStatus } from '../api/taskDelayRequests';
import { useAuth } from '../context/useAuth';
import { DELAY_REQUEST_STATUS_BADGE_CLASSES, DELAY_REQUEST_STATUS_LABELS } from '../lib/task-delay-request-status';
import useCursorList from '../hooks/useCursorList';
import Pager from '../components/Pager';

const STATUS_TOAST_MESSAGES = {
  approved: 'Đã duyệt yêu cầu gia hạn.',
  rejected: 'Đã từ chối yêu cầu gia hạn.',
  cancelled: 'Đã hủy yêu cầu gia hạn.',
};

// UpdateTaskDelayRequestStatusRequest only allows approved/rejected/cancelled
// (no reverting to pending), unlike leave-requests.
const ADMIN_STATUS_ACTIONS = [
  { status: 'approved', label: 'Duyệt', className: 'border-green-200 text-green-700 hover:bg-green-50' },
  { status: 'rejected', label: 'Từ chối', className: 'border-red-200 text-red-600 hover:bg-red-50' },
  { status: 'cancelled', label: 'Hủy', className: 'border-gray-300 text-gray-700 hover:bg-gray-100' },
];

/**
 * Flat top-level list, mirroring LeaveRequestsListPage (mục 5: "giống hệt
 * leave-requests"). Unlike leave-requests, a manager here sees every pending
 * request across every project (list isn't scoped to "their own" - see
 * GetTaskDelayRequestsRequest / mục 7.9), so this only exposes
 * approve/reject to admin (who bypasses every project-manager check via
 * before()) to avoid showing a manager a button that would 403 for a
 * project they don't manage. A manager reviews their own project's
 * requests from that task's detail modal on the project board instead,
 * where the PM check is computed correctly per-project.
 */
export default function TaskDelayRequestsListPage() {
  const { user } = useAuth();
  const isAdmin = user.position === 'admin';
  const canReview = user.position === 'manager' || isAdmin;

  const [status, setStatus] = useState('');
  const [actingId, setActingId] = useState(null);

  const {
    items: requests,
    meta,
    loading,
    refreshing,
    goToNext,
    goToPrev,
    replaceItem,
  } = useCursorList({
    fetcher: listTaskDelayRequests,
    params: { status: status || undefined },
    belongsInList: (request) => status === '' || request.status === status,
  });

  async function handleUpdateStatus(delayRequest, nextStatus) {
    if (nextStatus === 'cancelled' && !window.confirm('Hủy yêu cầu gia hạn này?')) {
      return;
    }

    setActingId(delayRequest.id);

    try {
      const updated = await updateTaskDelayRequestStatus(delayRequest.id, { status: nextStatus });
      toast.success(STATUS_TOAST_MESSAGES[nextStatus]);
      replaceItem(updated);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setActingId(null);
    }
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Yêu cầu gia hạn task</h1>
      </div>

      <div className="mb-4 flex gap-3">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          <option value="">Tất cả trạng thái</option>
          {Object.entries(DELAY_REQUEST_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-gray-200 bg-gray-50 text-gray-600">
            <tr>
              <th className="px-4 py-2 font-medium">Task</th>
              {canReview && <th className="px-4 py-2 font-medium">Người yêu cầu</th>}
              <th className="px-4 py-2 font-medium">Hạn hiện tại</th>
              <th className="px-4 py-2 font-medium">Hạn muốn dời</th>
              <th className="px-4 py-2 font-medium">Lý do</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
              <th className="px-4 py-2 font-medium">Hành động</th>
            </tr>
          </thead>
          <tbody
            className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
          >
            {loading && (
              <tr>
                <td colSpan={canReview ? 7 : 6} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && requests.length === 0 && (
              <tr>
                <td colSpan={canReview ? 7 : 6} className="px-4 py-6 text-center text-gray-500">
                  Không có yêu cầu gia hạn nào.
                </td>
              </tr>
            )}

            {!loading &&
              requests.map((request) => (
                <tr key={request.id} className="border-b border-gray-100 last:border-0">
                  <td className="px-4 py-2 text-gray-900">{request.task_title}</td>
                  {canReview && <td className="px-4 py-2 text-gray-500">{request.requester_name}</td>}
                  <td className="px-4 py-2 text-gray-500">{request.current_due_date}</td>
                  <td className="px-4 py-2 text-gray-500">{request.requested_due_date}</td>
                  <td className="px-4 py-2 text-gray-500">{request.reason}</td>
                  <td className="px-4 py-2">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${DELAY_REQUEST_STATUS_BADGE_CLASSES[request.status] ?? 'bg-gray-100 text-gray-700'}`}
                    >
                      {DELAY_REQUEST_STATUS_LABELS[request.status] ?? request.status}
                    </span>
                  </td>
                  <td className="px-4 py-2">
                    {request.status === 'pending' && (
                      <div className="flex gap-2">
                        {isAdmin &&
                          ADMIN_STATUS_ACTIONS.map((action) => (
                            <button
                              key={action.status}
                              type="button"
                              onClick={() => handleUpdateStatus(request, action.status)}
                              disabled={actingId === request.id}
                              className={`rounded-md border px-2 py-1 text-xs font-medium disabled:opacity-50 ${action.className}`}
                            >
                              {action.label}
                            </button>
                          ))}

                        {!isAdmin && request.requested_by === user.id && (
                          <button
                            type="button"
                            onClick={() => handleUpdateStatus(request, 'cancelled')}
                            disabled={actingId === request.id}
                            className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                          >
                            Hủy
                          </button>
                        )}
                      </div>
                    )}
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      <Pager
        hasPrev={Boolean(meta.prev_cursor)}
        hasNext={Boolean(meta.next_cursor)}
        onPrev={goToPrev}
        onNext={goToNext}
      />
    </div>
  );
}
