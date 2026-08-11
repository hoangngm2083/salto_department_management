import { useEffect, useRef, useState } from 'react';
import { listNotifications, markNotificationsAsRead, updateNotificationStatus } from '../api/notifications';

const POLL_INTERVAL_MS = Number(import.meta.env.VITE_NOTIFICATION_POLL_INTERVAL_MS) || 60000;

function NotificationText({ notification }) {
  const { type, data } = notification;

  if (type === 'LeaveRequestSubmitted') {
    return (
      <span className="text-gray-700">
        <strong className="font-medium text-gray-900">{data.employee_name}</strong> vừa gửi yêu cầu nghỉ phép từ{' '}
        {data.start_date} đến {data.end_date}.
      </span>
    );
  }

  if (type === 'LeaveRequestReviewed') {
    const statusLabel = data.status === 'approved' ? 'được duyệt' : 'bị từ chối';

    return (
      <span className="text-gray-700">
        Yêu cầu nghỉ phép của bạn đã <strong className="font-medium text-gray-900">{statusLabel}</strong> bởi{' '}
        {data.reviewed_by}.
      </span>
    );
  }

  return <span className="text-gray-700">Bạn có một thông báo mới.</span>;
}

export default function NotificationBell() {
  const [open, setOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const containerRef = useRef(null);

  useEffect(() => {
    async function fetchNotifications() {
      try {
        const result = await listNotifications();
        setNotifications(result.data);
        setUnreadCount(result.unread_count);
      } catch {
        // http.js interceptor already shows a toast for the error
      }
    }

    fetchNotifications();
    const interval = setInterval(fetchNotifications, POLL_INTERVAL_MS);

    // Background tabs get throttled by the browser, so a fixed interval alone
    // can leave the badge stale well past POLL_INTERVAL_MS. Refetch as soon as
    // the tab regains focus/visibility to close that gap.
    function handleVisibilityOrFocus() {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    }

    document.addEventListener('visibilitychange', handleVisibilityOrFocus);
    window.addEventListener('focus', handleVisibilityOrFocus);

    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', handleVisibilityOrFocus);
      window.removeEventListener('focus', handleVisibilityOrFocus);
    };
  }, []);

  useEffect(() => {
    function handleClickOutside(e) {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);

    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  async function handleMarkRead(notification) {
    if (notification.read_at) {
      return;
    }

    try {
      await updateNotificationStatus(notification.id, 'read');
      const readAt = new Date().toISOString();
      setNotifications((prev) => prev.map((n) => (n.id === notification.id ? { ...n, read_at: readAt } : n)));
      setUnreadCount((count) => Math.max(0, count - 1));
    } catch {
      // http.js interceptor already shows a toast for the error
    }
  }

  async function handleMarkAllRead() {
    const unreadIds = notifications.filter((n) => !n.read_at).map((n) => n.id);

    if (unreadIds.length === 0) {
      return;
    }

    try {
      await markNotificationsAsRead(unreadIds);
      const readAt = new Date().toISOString();
      setNotifications((prev) => prev.map((n) => (unreadIds.includes(n.id) ? { ...n, read_at: readAt } : n)));
      setUnreadCount(0);
    } catch {
      // http.js interceptor already shows a toast for the error
    }
  }

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="relative rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
        aria-label="Thông báo"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.8"
          className="size-5"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M14.857 17.082a23.85 23.85 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
          />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute bottom-full left-0 z-50 mb-2 w-80 rounded-lg border border-gray-200 bg-white shadow-lg">
          <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2">
            <span className="text-sm font-semibold text-gray-900">Thông báo</span>
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="text-xs font-medium text-gray-600 hover:text-gray-900"
              >
                Đánh dấu đã đọc tất cả
              </button>
            )}
          </div>

          <div className="max-h-96 overflow-y-auto">
            {notifications.length === 0 ? (
              <p className="px-4 py-6 text-center text-sm text-gray-500">Không có thông báo nào</p>
            ) : (
              notifications.map((notification) => (
                <button
                  key={notification.id}
                  type="button"
                  onClick={() => handleMarkRead(notification)}
                  className="flex w-full items-start gap-2 border-b border-gray-50 px-4 py-3 text-left text-sm last:border-b-0 hover:bg-gray-50"
                >
                  <span
                    className={`mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full ${
                      notification.read_at ? 'bg-transparent' : 'bg-gray-900'
                    }`}
                  />
                  <span className="flex-1">
                    <NotificationText notification={notification} />
                    <span className="mt-1 block text-xs text-gray-400">
                      {new Date(notification.created_at).toLocaleString('vi-VN')}
                    </span>
                  </span>
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
