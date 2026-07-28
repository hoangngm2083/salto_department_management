import { useEffect, useState } from 'react';
import { login as loginApi, logout as logoutApi, me } from '../api/auth';
import { getToken } from '../lib/auth-storage';
import { AuthContext } from './auth-context';

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(() => Boolean(getToken()));

  useEffect(() => {
    if (!loading) {
      return;
    }

    me()
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, [loading]);

  async function login(credentials) {
    const employee = await loginApi(credentials);
    setUser(employee);

    return employee;
  }

  async function logout() {
    try {
      await logoutApi();
    } finally {
      setUser(null);
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
