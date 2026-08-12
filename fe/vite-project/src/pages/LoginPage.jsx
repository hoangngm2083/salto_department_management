import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/useAuth';
import { consumeRedirectPath, endLogoutGuard } from '../lib/auth-storage';
import { roleHomePath } from '../lib/role-redirect';

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Actually mounting here is the one reliable signal that we've fully left
  // whatever protected route we were on - clearing the logout guard any
  // earlier (e.g. right after the navigate() call in Header) races with when
  // React actually flushes the transitional render, per auth-storage.js.
  useEffect(() => {
    endLogoutGuard();
  }, []);

  // Only reacts to the initial session check (loading flips once on mount), so it
  // won't fire again - and race with the redirect below - once handleSubmit sets user.
  useEffect(() => {
    if (!loading && user) {
      navigate(roleHomePath(user), { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loading]);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    try {
      const employee = await login({ email, password });
      const redirectPath = consumeRedirectPath(null);
      navigate(redirectPath || roleHomePath(employee), { replace: true });
    } catch {
      // http.js interceptor already shows a toast for the error
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
      >
        <h1 className="mb-6 text-xl font-semibold text-gray-900">Đăng nhập</h1>

        <label className="mb-4 block">
          <span className="mb-1 block text-sm font-medium text-gray-700">Email</span>
          <input
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>

        <label className="mb-6 block">
          <span className="mb-1 block text-sm font-medium text-gray-700">Mật khẩu</span>
          <input
            type="password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50 hover:bg-gray-700"
        >
          {submitting ? 'Đang đăng nhập...' : 'Đăng nhập'}
        </button>
      </form>
    </div>
  );
}
