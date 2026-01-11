<script setup>
import { onMounted, ref } from 'vue';
import httpClient from './api/httpClient';

const health = ref(null);
const message = ref('Hello from frontend');
const echoResponse = ref('');
const error = ref('');
const loading = ref(false);

const loadHealth = async () => {
  error.value = '';
  loading.value = true;
  try {
    const { data } = await httpClient.get('/health');
    health.value = data;
  } catch (err) {
    error.value = 'Failed to reach /api/health.';
    health.value = null;
  } finally {
    loading.value = false;
  }
};

const sendEcho = async () => {
  error.value = '';
  loading.value = true;
  try {
    const payload = {
      message: message.value,
      sentAt: new Date().toISOString(),
    };
    const { data } = await httpClient.post('/echo', payload);
    echoResponse.value = JSON.stringify(data, null, 2);
  } catch (err) {
    error.value = 'Failed to reach /api/echo.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadHealth);
</script>

<template>
  <main class="app">
    <header class="header">
      <p class="tag">sub_users</p>
      <h1>Frontend + Backend Link</h1>
      <p class="subtitle">Quick test page for the new project skeleton.</p>
    </header>

    <section class="card">
      <div class="card-header">
        <h2>API Health</h2>
        <button class="btn" type="button" @click="loadHealth" :disabled="loading">
          Refresh
        </button>
      </div>
      <p class="status" :class="{ ok: health }">
        {{ health ? `OK - ${health.app}` : 'Loading...' }}
      </p>
      <p class="meta" v-if="health">{{ health.time }}</p>
    </section>

    <section class="card">
      <div class="card-header">
        <h2>Echo Test</h2>
        <button class="btn" type="button" @click="sendEcho" :disabled="loading">
          Send
        </button>
      </div>
      <label class="label" for="message">Message</label>
      <input id="message" class="input" v-model="message" placeholder="Type a message" />
      <pre v-if="echoResponse" class="code">{{ echoResponse }}</pre>
      <p v-if="error" class="error">{{ error }}</p>
    </section>
  </main>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600&display=swap');

:root {
  color-scheme: light;
  font-family: 'Space Grotesk', sans-serif;
  background-color: #f7f5ef;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  background: radial-gradient(circle at top, #fff7e9 0%, #f4efe4 45%, #e9e4d7 100%);
  color: #1f2a30;
}

#app {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 20px;
}

.app {
  width: min(840px, 100%);
  display: grid;
  gap: 20px;
}

.header {
  display: grid;
  gap: 8px;
}

.tag {
  text-transform: uppercase;
  letter-spacing: 0.24em;
  font-size: 12px;
  font-weight: 600;
  color: #8b6f42;
  margin: 0;
}

h1 {
  font-size: clamp(28px, 3vw, 36px);
  margin: 0;
}

.subtitle {
  margin: 0;
  color: #4d5b63;
}

.card {
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #e5ddcf;
  border-radius: 18px;
  padding: 20px;
  box-shadow: 0 16px 40px rgba(72, 55, 22, 0.08);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

h2 {
  margin: 0;
  font-size: 20px;
}

.label {
  display: block;
  margin: 12px 0 6px;
  font-size: 14px;
  color: #5b5f53;
}

.input {
  width: 100%;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid #d8d0c1;
  background: #fffdf8;
  font-size: 15px;
}

.btn {
  border: none;
  border-radius: 999px;
  padding: 8px 18px;
  font-weight: 600;
  background: #1f2a30;
  color: #fff;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.status {
  font-size: 16px;
  margin: 16px 0 4px;
  color: #8a4b2d;
}

.status.ok {
  color: #1a6a47;
}

.meta {
  margin: 0;
  font-size: 13px;
  color: #5f6b72;
}

.code {
  background: #131a1e;
  color: #f7f3e6;
  padding: 14px;
  border-radius: 12px;
  font-size: 13px;
  margin: 14px 0 0;
  overflow-x: auto;
}

.error {
  color: #a22727;
  margin: 10px 0 0;
}
</style>