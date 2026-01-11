<template>
  <div class="app-layout">
    <Sidebar />
    <div class="app-main">
      <Topbar />
      <main class="app-content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import Sidebar from './Sidebar.vue';
import Topbar from './Topbar.vue';
</script>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}

.app-main {
  margin-left: 220px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.app-content {
  padding: 24px;
  flex: 1;
}

@media (max-width: 768px) {
  .app-main {
    margin-left: 0;
  }

  .app-layout {
    flex-direction: column;
  }

  .app-content {
    padding: 16px;
  }
}
</style>
