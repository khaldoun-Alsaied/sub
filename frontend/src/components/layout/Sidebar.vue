<template>
  <aside class="sidebar">
    <div class="sidebar__brand">Admin</div>
    <nav class="sidebar__nav">
      <RouterLink to="/dashboard" class="sidebar__link">Dashboard</RouterLink>
      <RouterLink to="/transactions" class="sidebar__link">Transactions</RouterLink>
      <RouterLink to="/manual-balance" class="sidebar__link">Manual Balance</RouterLink>
      <RouterLink to="/settings/users" class="sidebar__link">Users Settings</RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 220px;
  background: #0f172a;
  color: #e2e8f0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 24px 16px;
  position: fixed;
  top: 0;
  left: 0;
}

.sidebar__brand {
  font-weight: 700;
  letter-spacing: 0.4px;
  margin-bottom: 32px;
}

.sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sidebar__link {
  color: inherit;
  text-decoration: none;
  padding: 10px 12px;
  border-radius: 8px;
  transition: background 0.2s ease, color 0.2s ease;
}

.sidebar__link:hover {
  background: rgba(255, 255, 255, 0.08);
}

.router-link-active {
  background: #1d4ed8;
  color: #fff;
}
</style>
