import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AppLayout from '../layouts/AppLayout.vue';
import { isAuthenticated } from '../utils/auth';

const LoginView = () => import('../views/LoginView.vue');
const DashboardView = () => import('../views/DashboardView.vue');
const TransactionsView = () => import('../views/TransactionsView.vue');
const ManualBalanceView = () => import('../views/ManualBalanceView.vue');
const ActivityLogView = () => import('../views/ActivityLog.vue');
const UsersSettingsView = () => import('../views/UsersSettingsView.vue');
const ExpensesView = () => import('../views/ExpensesView.vue');
const ExpensesSettingsView = () => import('../views/ExpensesSettingsView.vue');

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { title: 'Login' },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: { name: 'dashboard' },
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView,
          meta: { requiresAuth: true, title: 'Dashboard' },
        },
        {
          path: 'transactions',
          name: 'transactions',
          component: TransactionsView,
          meta: { requiresAuth: true, title: 'Transactions' },
        },
        {
          path: 'expenses',
          name: 'expenses',
          component: ExpensesView,
          meta: { requiresAuth: true, title: 'Expenses' },
        },
        {
          path: 'manual-balance',
          name: 'manual-balance',
          component: ManualBalanceView,
          meta: { requiresAuth: true, title: 'Manual Balance' },
        },
        {
          path: 'activity-log',
          name: 'activity-log',
          component: ActivityLogView,
          meta: { requiresAuth: true, title: 'Activity Log' },
        },
        {
          path: 'settings/users',
          name: 'users-settings',
          component: UsersSettingsView,
          meta: { requiresAuth: true, requiresAdmin: true, title: 'Users Settings' },
        },
        {
          path: 'settings/expenses',
          name: 'expenses-settings',
          component: ExpensesSettingsView,
          meta: { requiresAuth: true, requiresAdmin: true, title: 'Expenses Settings' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'dashboard' },
    },
  ],
});

router.beforeEach((to, from, next) => {
  const auth = useAuthStore();
  const authed = auth.isAuthenticated || isAuthenticated();
  const role = auth.user?.role;
  const allowedRoutes = auth.allowedRoutes || [];

  if (to.name === 'login' && authed) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.requiresAuth && !authed) {
    return next({ name: 'login' });
  }

  if (to.meta.requiresAdmin && role !== 'admin') {
    return next({ name: 'dashboard' });
  }

  if (role === 'viewer') {
    const target = to.name;
    const whitelist = Array.isArray(allowedRoutes) ? allowedRoutes : [];
    if (target && !whitelist.includes(target)) {
      const fallback = whitelist[0] ? { name: whitelist[0] } : { name: 'dashboard' };
      return next(fallback);
    }
  }

  return next();
});


export default router;
