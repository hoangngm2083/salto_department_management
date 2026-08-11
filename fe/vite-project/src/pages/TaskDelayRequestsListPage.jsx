import { useState } from 'react';
import { toast } from 'sonner';
import { listTaskDelayRequests, updateTaskDelayRequestStatus } from '../api/taskDelayRequests';
import { useAuth } from '../context/useAuth';
import { DELAY_REQUEST_STATUS_BADGE_CLASSES, DELAY_REQUEST_STATUS_LABELS } from '../lib/task-delay-request-status';
import useCursorList from '../hooks/useCursorList';
import Pager from '../components/Pager';
import StatusDropdown from '../components/StatusDropdown';

const STATUS_TOAST_MESSAGES = {
  approved: 'Đã duyệt yêu cầu gia hạn.',
  rejected: 'Đã từ chối yêu cầu gia hạn.',
  cancelled: 'Đã hủy yêu cầu gia hạn.',
};

// UpdateTaskDelayRequestStatusRequest only allows approved/rejected/cancelled
// (no reverting to pending), unlike leave-requests.
const ADMIN_ACTIONABLE_STATUSES = ['approved', 'rejected', 'cancelled'];

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

  /**
   * Which statuses `request` may be moved to by the current actor - only
   * ever non-empty while still pending. Admin may approve/reject/cancel any
   * request; the requester may only cancel their own. A manager reviews
   * their own project's requests from the task's detail modal instead (see
   * the module docblock), so they get no options here even if `canReview`.
   */
  function getAvailableStatuses(request) {
    if (request.status !== 'pending') {
      return [];
    }

    if (isAdmin) {
      return ADMIN_ACTIONABLE_STATUSES;
    }

    if (request.requested_by === user.id) {
      return ['cancelled'];
    }

    return [];
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
            </tr>
          </thead>
          <tbody
            className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
          >
            {loading && (
              <tr>
                <td colSpan={canReview ? 6 : 5} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && requests.length === 0 && (
              <tr>
                <td colSpan={canReview ? 6 : 5} className="px-4 py-6 text-center text-gray-500">
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
                    <StatusDropdown
                      status={request.status}
                      statusLabels={DELAY_REQUEST_STATUS_LABELS}
                      statusBadgeClasses={DELAY_REQUEST_STATUS_BADGE_CLASSES}
                      options={getAvailableStatuses(request)}
                      busy={actingId === request.id}
                      onSelect={(nextStatus) => handleUpdateStatus(request, nextStatus)}
                    />
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
