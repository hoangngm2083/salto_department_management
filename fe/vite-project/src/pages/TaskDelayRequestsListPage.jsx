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
// (no reverting to pending).
const ADMIN_ACTIONABLE_STATUSES = ['approved', 'rejected', 'cancelled'];
const REVIEWER_ACTIONABLE_STATUSES = ['approved', 'rejected'];

/**
 * Flat top-level list (mục 5: task delay requests stay a single-step flat-status
 * resource, unlike Leave Request which migrated onto the Approval Engine in Phase E.5).
 * GetTaskDelayRequestsRequest scopes a manager to requests on projects they actively
 * manage plus any request they submitted themselves, so every row a manager sees here is
 * one they have authority over - approve/reject shows for rows on their managed
 * project(s), cancel shows for rows they submitted themselves.
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
   * request. A manager may approve/reject a request on a project they
   * manage (every non-self row a manager sees here is one, per the backend
   * scoping - see the module docblock); the requester may only cancel their
   * own, whether that requester is an employee or a manager reviewing
   * someone else's tasks elsewhere.
   */
  function getAvailableStatuses(request) {
    if (request.status !== 'pending') {
      return [];
    }

    if (isAdmin) {
      return ADMIN_ACTIONABLE_STATUSES;
    }

    const actions = [];

    if (canReview && request.requested_by !== user.id) {
      actions.push(...REVIEWER_ACTIONABLE_STATUSES);
    }

    if (request.requested_by === user.id) {
      actions.push('cancelled');
    }

    return actions;
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
