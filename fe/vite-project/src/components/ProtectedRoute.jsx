import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/useAuth';
import { saveRedirectPath } from '../lib/auth-storage';
import { roleHomePath } from '../lib/role-redirect';

export default function ProtectedRoute({ roles }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-gray-500">
        Đang tải...
      </div>
    );
  }

  if (!user) {
    // This also re-renders with user=null for a tick during a deliberate logout
    // (while still on the old protected route). AuthContext's logout() arms
    // suppressNextRedirectSave() beforehand so that specific save is swallowed,
    // while a genuine "opened a protected link while logged out" visit still
    // gets remembered here.
    saveRedirectPath(location.pathname + location.search);

    return <Navigate to="/login" replace />;
  }

  if (roles && !roles.includes(user.position)) {
    return <Navigate to={roleHomePath(user)} replace />;
  }

  return <Outlet />;
}
