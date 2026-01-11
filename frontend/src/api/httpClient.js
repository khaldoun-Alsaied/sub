import axios from 'axios';
import { TOKEN_KEY, clearToken } from '@/utils/auth';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';
const BASE_PATH = (import.meta.env.BASE_URL || '/').replace(/\/+$/, '/');
const USER_KEY = 'auth_user';

const httpClient = axios.create({
  baseURL: API_BASE_URL,
});

httpClient.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

httpClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;
    if (status === 401) {
      // فقط نمرر الخطأ؛ يمكن للمكونات إظهار رسالة أو طلب تسجيل دخول يدوي
      // لتجنب تسجيل خروج غير مقصود أثناء تصفح صفحات محمية
    }
    return Promise.reject(error);
  }
);

export default httpClient;
