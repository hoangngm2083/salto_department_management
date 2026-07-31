import axios from 'axios';
import { toast } from 'sonner';
import { clearToken, getToken, saveRedirectPath } from './auth-storage';

export const LOGIN_PATH = '/login';

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    Accept: 'application/json',
  },
});

http.interceptors.request.use((config) => {
  const token = getToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

let isRedirectingToLogin = false;

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response } = error;

    if (!response) {
      toast.error('Network error', {
        description: 'Could not reach the server. Please check your connection.',
      });

      return Promise.reject(error);
    }

    // A request made with responseType: 'blob' (e.g. CSV export downloads)
    // still gets its error body back as a Blob, even when the server sent
    // JSON. Parse it back so the messages below aren't just "undefined".
    let { data } = response;

    if (data instanceof Blob && data.type.includes('json')) {
      data = JSON.parse(await data.text());
    }

    const { status } = response;
    const message = data?.message || error.message || 'Something went wrong.';

    if (status === 401) {
      clearToken();

      const isOnLoginPage = window.location.pathname === LOGIN_PATH;

      if (!isOnLoginPage) {
        toast.error(`${status}: ${message}`);

        if (!isRedirectingToLogin) {
          isRedirectingToLogin = true;
          saveRedirectPath(window.location.pathname + window.location.search);
          window.location.href = LOGIN_PATH;
        }
      }

      return Promise.reject(error);
    }

    if (status === 422) {
      const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;

      toast.error(`${status}: ${message}`, {
        description: firstError && firstError !== message ? firstError : undefined,
      });

      return Promise.reject(error);
    }

    toast.error(`${status}: ${message}`);

    return Promise.reject(error);
  }
);

export default http;
