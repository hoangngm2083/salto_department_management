const TOKEN_KEY = 'auth_token';
const REDIRECT_KEY = 'auth_redirect_path';

let isLoggingOut = false;

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY);
}

/**
 * Marks a deliberate logout as in progress, so saveRedirectPath() becomes a
 * no-op for its duration.
 *
 * ProtectedRoute re-renders with a null user (possibly more than once - React
 * StrictMode double-invokes renders in dev) while still on the old protected
 * route, before the router has switched to /login. Left unguarded, that would
 * save "where I was" as a resumable path even though the user chose to leave.
 *
 * Call this before starting the logout API call. Only clear it (via
 * endLogoutGuard) once LoginPage has actually mounted - not right after the
 * navigate() call in Header, since React flushes ProtectedRoute's
 * transitional re-render later than that line runs, and the guard would
 * already be back to false by the time the leaky render happens.
 */
export function beginLogoutGuard() {
  isLoggingOut = true;
}

export function endLogoutGuard() {
  isLoggingOut = false;
}

export function saveRedirectPath(path) {
  if (isLoggingOut) {
    return;
  }

  sessionStorage.setItem(REDIRECT_KEY, path);
}

export function consumeRedirectPath(fallback = '/') {
  const path = sessionStorage.getItem(REDIRECT_KEY);
  sessionStorage.removeItem(REDIRECT_KEY);
  return path || fallback;
}
