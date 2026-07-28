import http from '../lib/http';
import { clearToken, setToken } from '../lib/auth-storage';

export async function login({ email, password, deviceName = 'web' }) {
  const { data } = await http.post('/auth/login', {
    email,
    password,
    device_name: deviceName,
  });

  setToken(data.data.token);

  return data.data.employee;
}

export async function logout() {
  await http.post('/auth/logout');
  clearToken();
}

export async function me() {
  const { data } = await http.get('/auth/me');

  return data.data;
}
