import { useState } from 'react';
import { toast } from 'sonner';
import { listLeaveRequests, updateLeaveRequestStatus } from '../api/leaveRequests';
import { useAuth } from '../context/useAuth';
import { LEAVE_STATUS_BADGE_CLASSES, LEAVE_STATUS_LABELS } from '../lib/leave-request-status';
import useCursorList from '../hooks/useCursorList';
import CreateLeaveRequestModal from '../components/CreateLeaveRequestModal';
import Pager from '../components/Pager';

const STATUS_TOAST_MESSAGES = {
  pending: 'Đã chuyển đơn về trạng thái chờ duyệt.',
  approved: 'Đã duyệt đơn nghỉ phép.',
  rejected: 'Đã từ chối đơn nghỉ phép.',
  cancelled: 'Đã hủy đơn nghỉ phép.',
};

const ADMIN_STATUS_ACTIONS = [
  { status: 'pending', label: 'Chờ duyệt', className: 'border-yellow-200 text-yellow-700 hover:bg-yellow-50' },
  { status: 'approved', label: 'Duyệt', className: 'border-green-200 text-green-700 hover:bg-green-50' },
  { status: 'rejected', label: 'Từ chối', className: 'border-red-200 text-red-600 hover:bg-red-50' },
  { status: 'cancelled', label: 'Hủy đơn', className: 'border-gray-300 text-gray-700 hover:bg-gray-100' },
];

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
    if (nextStatus === 'cancelled' && !window.confirm('Hủy đơn nghỉ phép này?')) {
      return;
    }

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

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Đơn nghỉ phép</h1>
        <button
          type="button"
          onClick={() => setShowCreate(true)}
          className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
        >
          Tạo đơn nghỉ phép
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
              <th className="px-4 py-2 font-medium">Hành động</th>
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
                  Không có đơn nghỉ phép nào.
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
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${LEAVE_STATUS_BADGE_CLASSES[request.status] ?? 'bg-gray-100 text-gray-700'}`}
                    >
                      {LEAVE_STATUS_LABELS[request.status] ?? request.status}
                    </span>
                  </td>
                  <td className="px-4 py-2">
                    <div className="flex gap-2">
                      {isAdmin &&
                        ADMIN_STATUS_ACTIONS.filter((action) => action.status !== request.status).map((action) => (
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

                      {!isAdmin && request.status === 'pending' && (
                        <>
                          {canReview && (
                            <>
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(request, 'approved')}
                                disabled={actingId === request.id}
                                className="rounded-md border border-green-200 px-2 py-1 text-xs font-medium text-green-700 disabled:opacity-50 hover:bg-green-50"
                              >
                                Duyệt
                              </button>
                              <button
                                type="button"
                                onClick={() => handleUpdateStatus(request, 'rejected')}
                                disabled={actingId === request.id}
                                className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-600 disabled:opacity-50 hover:bg-red-50"
                              >
                                Từ chối
                              </button>
                            </>
                          )}
                          {request.employee_id === user.id && (
                            <button
                              type="button"
                              onClick={() => handleUpdateStatus(request, 'cancelled')}
                              disabled={actingId === request.id}
                              className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 disabled:opacity-50 hover:bg-gray-100"
                            >
                              Hủy đơn
                            </button>
                          )}
                        </>
                      )}
                    </div>
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
