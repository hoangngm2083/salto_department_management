import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/useAuth';
import { beginLogoutGuard } from '../lib/auth-storage';
import { ROLE_LABELS } from '../lib/role-labels';

export default function Header() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    beginLogoutGuard();

    try {
      await logout();
    } finally {
      // The guard is lifted once LoginPage actually mounts (see its effect),
      // not here - React flushes ProtectedRoute's transitional re-render
      // (still against the old route) later than this line runs.
      navigate('/login', { replace: true });
    }
  }

  return (
    <header className="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
      <div className="flex items-center gap-6">
        <span className="text-lg font-semibold text-gray-900">Department Management</span>

        {user?.position === 'admin' && (
          <nav className="flex items-center gap-4 text-sm font-medium text-gray-600">
            <Link to="/departments" className="hover:text-gray-900">
              Phòng ban
            </Link>
            <Link to="/employees" className="hover:text-gray-900">
              Người dùng
            </Link>
          </nav>
        )}
      </div>

      {user && (
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-600">
            {user.name}{' '}
            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
              {ROLE_LABELS[user.position] ?? user.position}
            </span>
          </span>
          <button
            type="button"
            onClick={handleLogout}
            className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
          >
            Đăng xuất
          </button>
        </div>
      )}
    </header>
  );
}
