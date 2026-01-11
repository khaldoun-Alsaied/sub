<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const userName = computed(() => authStore.user?.name || 'User');

function handleLogout() {
  authStore.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <header class="topbar">
    <div class="topbar__title">Company Accounts Dashboard</div>
    <div class="topbar__actions">
      <span class="topbar__user">Hi, {{ userName }}</span>
      <button class="topbar__logout" type="button" @click="handleLogout">
        Logout
      </button>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 10;
}

.topbar__title {
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
}

.topbar__actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar__user {
  color: #475569;
  font-size: 14px;
}

.topbar__logout {
  border: none;
  background: #ef4444;
  color: #fff;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s ease;
}

.topbar__logout:hover {
  background: #dc2626;
}
</style>
