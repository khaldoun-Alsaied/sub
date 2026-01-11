<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const errorMessage = ref('');
const loading = ref(false);
const successMessage = ref('');

const handleSubmit = async () => {
  errorMessage.value = '';
  successMessage.value = '';
  loading.value = true;
  try {
    await authStore.login(email.value, password.value);
    successMessage.value = 'تم تسجيل الدخول بنجاح';
    router.push('/dashboard');
  } catch (err) {
    console.error(err);
    errorMessage.value = 'Login failed';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <h1>Login</h1>
      <form class="login-form" @submit.prevent="handleSubmit">
        <label>
          Email
          <input v-model="email" type="email" autocomplete="email" required />
        </label>
        <label>
          Password
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
        <button type="submit" :disabled="loading">
          {{ loading ? 'Logging in...' : 'Login' }}
        </button>
        <p v-if="successMessage" class="success">{{ successMessage }}</p>
        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  padding: 16px;
}

.login-card {
  width: 100%;
  max-width: 400px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
}

.login-card h1 {
  margin: 0 0 16px;
  font-size: 22px;
  color: #0f172a;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

label {
  display: flex;
  flex-direction: column;
  font-size: 14px;
  color: #475569;
  gap: 6px;
}

input {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

button {
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #2563eb;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.2s ease;
}

button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

button:not(:disabled):hover {
  background: #1d4ed8;
}

.error {
  color: #dc2626;
  margin: 4px 0 0;
  font-size: 14px;
}
</style>
