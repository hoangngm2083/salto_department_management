import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/useAuth';

/**
 * `/me` is just a self-pointing alias - it always redirects to the same
 * `/employees/:id` page any other profile view uses, so there's a single
 * profile implementation (with work history) instead of two diverging ones.
 */
export default function MePage() {
  const { user } = useAuth();

  if (!user) {
    return null;
  }

  return <Navigate to={`/employees/${user.id}`} replace />;
}
