import { defineStore } from 'pinia';
import httpClient from '../api/httpClient';
import { getToken, setToken, clearToken } from '../utils/auth';

const USER_KEY = 'auth_user';

const loadUser = () => {
  try {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
};

const saveUser = (user) => {
  localStorage.setItem(USER_KEY, JSON.stringify(user));
};

const clearUser = () => localStorage.removeItem(USER_KEY);

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: loadUser(),
    token: getToken(),
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    allowedRoutes: (state) => {
      const routes = state.user?.allowed_routes;
      if (Array.isArray(routes)) return routes;
      if (typeof routes === 'string') {
        try {
          const parsed = JSON.parse(routes);
          if (Array.isArray(parsed)) return parsed;
        } catch {
          // ignore parse errors
        }
        return routes.split(',').map((r) => r.trim()).filter(Boolean);
      }
      return [];
    },
  },
  actions: {
    async login(email, password) {
      const { data } = await httpClient.post('/auth/login', { email, password });
      this.token = data.token;
      this.user = data.user;
      setToken(data.token);
      saveUser(data.user);
    },
    logout() {
      this.user = null;
      this.token = null;
      clearToken();
      clearUser();
    },
  },
});
