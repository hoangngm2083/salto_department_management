import { useState } from 'react';
import { toast } from 'sonner';
import { listLeaveRequests, updateLeaveRequestStatus } from '../api/leaveRequests';
import { useAuth } from '../context/useAuth';
import { LEAVE_STATUS_BADGE_CLASSES, LEAVE_STATUS_LABELS } from '../lib/leave-request-status';
import useCursorList from '../hooks/useCursorList';
import CreateLeaveRequestModal from '../components/CreateLeaveRequestModal';
import Pager from '../components/Pager';
import StatusDropdown from '../components/StatusDropdown';

const STATUS_TOAST_MESSAGES = {
  pending: 'Đã chuyển yêu cầu về trạng thái chờ duyệt.',
  approved: 'Đã duyệt yêu cầu nghỉ phép.',
  rejected: 'Đã từ chối yêu cầu nghỉ phép.',
  cancelled: 'Đã hủy yêu cầu nghỉ phép.',
};

const ALL_STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

export default function LeaveRequestsListPage() {
  const { user } = useAuth();
  const isAdmin = user.position === 'admin';
  const canReview = user.position === 'manager' || isAdmin;

  const [status, setStatus] = useState('');
  const [actingId, setActingId] = useState(null);
  const [showCreate, setShowCreate] = useState(false);

  const {
    items: requests,
    meta,
    loading,
    refreshing,
    goToNext,
    goToPrev,
    refresh,
    replaceItem,
  } = useCursorList({
    fetcher: listLeaveRequests,
    params: { status: status || undefined },
    belongsInList: (request) => status === '' || request.status === status,
  });

  async function handleUpdateStatus(leaveRequest, nextStatus) {
    setActingId(leaveRequest.id);

    try {
      const updated = await updateLeaveRequestStatus(leaveRequest.id, { status: nextStatus });
      toast.success(STATUS_TOAST_MESSAGES[nextStatus]);
      replaceItem(updated);
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setActingId(null);
    }
  }

  /**
   * Which statuses `request` may be moved to by the current actor - admin
   * may move a request between any two statuses; a manager may only
   * approve/reject their own department's still-pending requests; the
   * request's own employee may only cancel their own still-pending one.
   * Feeds `StatusDropdown`, which also covers the "no options" (read-only
   * badge) case.
   */
  function getAvailableStatuses(request) {
    if (isAdmin) {
      return ALL_STATUSES.filter((value) => value !== request.status);
    }

    if (request.status !== 'pending') {
      return [];
    }

    const options = [];

    if (canReview) {
      options.push('approved', 'rejected');
    }

    if (request.employee_id === user.id) {
      options.push('cancelled');
    }

    return options;
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Yêu cầu nghỉ phép</h1>
        <button
          type="button"
          onClick={() => setShowCreate(true)}
          className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
        >
          Tạo yêu cầu nghỉ phép
        </button>
      </div>

      <div className="mb-4 flex gap-3">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm"
        >
          <option value="">Tất cả trạng thái</option>
          {Object.entries(LEAVE_STATUS_LABELS).map(([value, label]) => (
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
              {canReview && <th className="px-4 py-2 font-medium">Nhân viên</th>}
              <th className="px-4 py-2 font-medium">Từ ngày</th>
              <th className="px-4 py-2 font-medium">Đến ngày</th>
              <th className="px-4 py-2 font-medium">Lý do</th>
              <th className="px-4 py-2 font-medium">Trạng thái</th>
            </tr>
          </thead>
          <tbody
            className={`transition-opacity duration-150 ${refreshing ? 'opacity-50' : 'opacity-100'}`}
          >
            {loading && (
              <tr>
                <td colSpan={canReview ? 5 : 4} className="px-4 py-6 text-center text-gray-500">
                  Đang tải...
                </td>
              </tr>
            )}

            {!loading && requests.length === 0 && (
              <tr>
                <td colSpan={canReview ? 5 : 4} className="px-4 py-6 text-center text-gray-500">
                  Không có yêu cầu nghỉ phép nào.
                </td>
              </tr>
            )}

            {!loading &&
              requests.map((request) => (
                <tr key={request.id} className="border-b border-gray-100 last:border-0">
                  {canReview && (
                    <td className="px-4 py-2">
                      {request.employee_name}
                      <div className="text-xs text-gray-500">{request.department_name}</div>
                    </td>
                  )}
                  <td className="px-4 py-2 text-gray-500">{request.start_date}</td>
                  <td className="px-4 py-2 text-gray-500">{request.end_date}</td>
                  <td className="px-4 py-2 text-gray-500">{request.reason}</td>
                  <td className="px-4 py-2">
                    <StatusDropdown
                      status={request.status}
                      statusLabels={LEAVE_STATUS_LABELS}
                      statusBadgeClasses={LEAVE_STATUS_BADGE_CLASSES}
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

      {showCreate && (
        <CreateLeaveRequestModal onClose={() => setShowCreate(false)} onCreated={refresh} />
      )}
    </div>
  );
}
