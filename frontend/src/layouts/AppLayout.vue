<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const isSidebarOpen = ref(false);

const sectionTitle = computed(() => route.meta?.title || 'Dashboard');
const currentUser = computed(() => authStore.user);
const allowedRoutes = computed(() => authStore.allowedRoutes || []);

const canSee = (routeName) => {
  if (currentUser.value?.role === 'admin') return true;
  const routes = allowedRoutes.value && allowedRoutes.value.length ? allowedRoutes.value : ['dashboard', 'transactions', 'expenses'];
  return routes.includes(routeName);
};

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

const handleNav = () => {
  if (window.innerWidth <= 900) {
    isSidebarOpen.value = false;
  }
};

const handleLogout = () => {
  authStore.logout();
  router.push({ name: 'login' });
};
</script>

<template>
  <div class="layout">
    <aside class="sidebar" :class="{ 'sidebar--open': isSidebarOpen }">
      <div class="sidebar__brand">Company Accounts</div>
      <nav class="sidebar__nav">
        <RouterLink v-if="canSee('dashboard')" to="/dashboard" class="sidebar__link" active-class="sidebar__link--active" @click="handleNav">
          Dashboard
        </RouterLink>
        <RouterLink
          v-if="canSee('transactions')"
          to="/transactions"
          class="sidebar__link"
          active-class="sidebar__link--active"
          @click="handleNav"
        >
          Transactions
        </RouterLink>
        <RouterLink
          v-if="canSee('expenses')"
          to="/expenses"
          class="sidebar__link"
          active-class="sidebar__link--active"
          @click="handleNav"
        >
          Expenses
        </RouterLink>
        <RouterLink
          v-if="canSee('manual-balance')"
          to="/manual-balance"
          class="sidebar__link"
          active-class="sidebar__link--active"
          @click="handleNav"
        >
          Manual Balance
        </RouterLink>
        <RouterLink
          v-if="canSee('activity-log')"
          to="/activity-log"
          class="sidebar__link"
          active-class="sidebar__link--active"
          @click="handleNav"
        >
          Activity Log
        </RouterLink>
        <RouterLink
          v-if="currentUser?.role === 'admin'"
          to="/settings/users"
          class="sidebar__link"
          active-class="sidebar__link--active"
          @click="handleNav"
        >
          Users Settings
        </RouterLink>
        <RouterLink
          v-if="currentUser?.role === 'admin'"
          to="/settings/expenses"
          class="sidebar__link"
          active-class="sidebar__link--active"
          @click="handleNav"
        >
          Expenses Settings
        </RouterLink>
      </nav>
    </aside>
    <div v-if="isSidebarOpen" class="sidebar-overlay" @click="isSidebarOpen = false"></div>

    <div class="content">
      <header class="header">
        <div class="header__left">
          <button class="header__toggle" type="button" @click="toggleSidebar">
            ☰
          </button>
          <div>
            <div class="header__app-name">Company Accounts</div>
            <div class="header__section">{{ sectionTitle }}</div>
          </div>
        </div>
        <div class="header__right">
          <span class="header__greeting">Hi, {{ currentUser?.name || 'User' }}</span>
          <button class="header__logout" type="button" @click="handleLogout">
            Logout
          </button>
        </div>
      </header>

      <main class="main">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}

.sidebar {
  width: 240px;
  background: var(--color-white);
  color: var(--color-text);
  padding: 20px 16px;
  position: fixed;
  inset: 0 auto 0 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition: transform 0.2s ease;
}

.sidebar__brand {
  font-weight: 700;
  letter-spacing: 0.4px;
  color: var(--color-primary-dark);
}

.sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sidebar__link {
  color: inherit;
  padding: 10px 12px;
  border-radius: 8px;
  transition: background 0.2s ease, color 0.2s ease;
}

.sidebar__link:hover {
  background: var(--color-primary-light);
  color: var(--color-primary-dark);
}

.sidebar__link--active {
  background: var(--color-blush);
  color: var(--color-primary-dark);
}

.content {
  flex: 1;
  margin-left: 240px;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.header {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: var(--color-white);
  border-bottom: 3px solid var(--color-primary);
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
}

.header__left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header__toggle {
  display: none;
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 8px;
  padding: 8px 10px;
  cursor: pointer;
}

.header__app-name {
  font-weight: 700;
  color: var(--color-primary-dark);
}

.header__section {
  font-size: 14px;
  color: var(--color-text-muted);
}

.header__right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header__greeting {
  color: var(--color-text-muted);
  font-size: 14px;
}

.header__logout {
  border: none;
  background: var(--color-primary);
  color: #fff;
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s ease;
}

.header__logout:hover {
  background: var(--color-primary-dark);
}

.main {
  flex: 1;
  padding: 20px;
}

@media (max-width: 900px) {
  .sidebar {
    transform: translateX(-100%);
  }

  .sidebar--open {
    transform: translateX(0);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    z-index: 20;
  }

  .content {
    margin-left: 0;
  }

  .header__toggle {
    display: inline-flex;
  }
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  z-index: 15;
}
</style>
