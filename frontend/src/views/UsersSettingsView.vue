<script setup>
import { ref, computed, onMounted } from 'vue';
import httpClient from '@/api/httpClient';

const search = ref('');
const users = ref([]);
const loading = ref(false);
const error = ref('');
const modalOpen = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const form = ref({
  id: null,
  name: '',
  email: '',
  role: 'viewer',
  password: '',
  allowed_routes: [],
});

const isViewer = computed(() => form.value.role === 'viewer');

const availableRoutes = [
  { name: 'dashboard', label: 'Dashboard' },
  { name: 'transactions', label: 'Transactions' },
  { name: 'expenses', label: 'Expenses' },
  { name: 'manual-balance', label: 'Manual Balance' },
  { name: 'activity-log', label: 'Activity Log' },
];

const filteredUsers = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return users.value;
  return users.value.filter(
    (u) =>
      u.name.toLowerCase().includes(term) ||
      u.email.toLowerCase().includes(term) ||
      u.role.toLowerCase().includes(term)
  );
});

function resetForm() {
  form.value = {
    id: null,
    name: '',
    email: '',
    role: 'viewer',
    password: '',
    allowed_routes: [],
  };
  isEditing.value = false;
}

function openCreate() {
  resetForm();
  modalOpen.value = true;
}

function openEdit(user) {
  form.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    password: '',
    allowed_routes: Array.isArray(user.allowed_routes) ? user.allowed_routes : [],
  };
  isEditing.value = true;
  modalOpen.value = true;
}

async function fetchUsers() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await httpClient.get('/users');
    users.value = data || [];
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحميل المستخدمين';
  } finally {
    loading.value = false;
  }
}

async function saveUser() {
  saving.value = true;
  error.value = '';
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
    };
    if (isViewer.value) {
      if (!form.value.allowed_routes || !form.value.allowed_routes.length) {
        throw new Error('يجب اختيار صفحة واحدة على الأقل للـ Viewer');
      }
      payload.allowed_routes = form.value.allowed_routes;
    }
    if (!isEditing.value || form.value.password) {
      payload.password = form.value.password;
    }
    if (isEditing.value) {
      await httpClient.patch(`/users/${form.value.id}`, payload);
    } else {
      await httpClient.post('/users', payload);
    }
    modalOpen.value = false;
    await fetchUsers();
  } catch (err) {
    console.error(err);
    const msg = err?.response?.data?.error || 'تعذر حفظ المستخدم';
    error.value = msg;
  } finally {
    saving.value = false;
  }
}

async function deleteUser(userId) {
  if (!window.confirm('هل تريد حذف المستخدم؟')) return;
  error.value = '';
  try {
    await httpClient.delete(`/users/${userId}`);
    await fetchUsers();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر حذف المستخدم';
  }
}

onMounted(fetchUsers);
</script>

<template>
  <section class="settings">
    <header class="settings__header">
      <div>
        <h1 class="page-title">إعدادات المستخدمين</h1>
        <p class="text-muted">إدارة إنشاء المستخدمين والأدوار قبل ربط الـ API.</p>
      </div>
      <button class="primary-btn" type="button" @click="openCreate">+ مستخدم جديد</button>
    </header>

    <div class="card search-card">
      <label class="filter filter--grow">
        <span>بحث</span>
        <input v-model="search" type="text" placeholder="الاسم أو البريد أو الدور..." />
      </label>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="table-card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>الاسم</th>
              <th>البريد</th>
              <th>الدور</th>
              <th>الصفحات المسموح بها</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="muted">...جاري التحميل</td>
            </tr>
            <tr v-else-if="!filteredUsers.length">
              <td colspan="5" class="muted">لا يوجد مستخدمون.</td>
            </tr>
            <tr v-else v-for="user in filteredUsers" :key="user.id">
              <td>{{ user.name }}</td>
              <td>{{ user.email }}</td>
              <td>{{ user.role }}</td>
              <td class="allowed">
                <span v-if="user.role === 'admin'">جميع الصفحات</span>
                <span v-else>{{ (user.allowed_routes || []).join(', ') || '—' }}</span>
              </td>
              <td class="actions">
                <button class="link-btn" type="button" @click="openEdit(user)">تعديل</button>
                <button class="link-btn danger" type="button" @click="deleteUser(user.id)">حذف</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="modalOpen" class="modal">
      <div class="modal__overlay" @click="modalOpen = false"></div>
      <div class="modal__content">
        <div class="modal__header">
          <h2>{{ isEditing ? 'تعديل مستخدم' : 'إضافة مستخدم' }}</h2>
          <button class="close-btn" type="button" @click="modalOpen = false">×</button>
        </div>
        <form class="modal__form" @submit.prevent="saveUser">
          <label>
            الاسم
            <input v-model="form.name" type="text" required />
          </label>
          <label>
            البريد
            <input v-model="form.email" type="email" required />
          </label>
          <label>
            الدور
            <select v-model="form.role" required>
              <option value="admin">admin</option>
              <option value="viewer">viewer</option>
            </select>
          </label>
          <label>
            كلمة المرور
            <input v-model="form.password" type="password" :required="!isEditing" autocomplete="new-password" />
          </label>
          <div class="routes">
            <p class="routes__title">الصفحات المسموح بها (للـ viewer)</p>
            <div class="routes__list">
              <label v-for="route in availableRoutes" :key="route.name" class="route-item">
                <input
                  type="checkbox"
                  :value="route.name"
                  v-model="form.allowed_routes"
                  :disabled="form.role === 'admin'"
                />
                {{ route.label }}
              </label>
            </div>
          </div>
          <div class="modal__actions">
            <button class="secondary-btn" type="button" @click="modalOpen = false">إلغاء</button>
            <button class="primary-btn" type="submit" :disabled="saving">
              {{ saving ? 'جاري الحفظ...' : 'حفظ' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<style scoped>
.settings {
  display: grid;
  gap: 16px;
  padding-bottom: 12px;
}

.settings__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.search-card {
  padding: 12px 16px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
}

.filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 14px;
  color: #475569;
}

.filter input {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
}

.table-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}

.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 680px;
}

th,
td {
  padding: 12px 14px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  font-size: 14px;
}

th {
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
}

.actions {
  display: flex;
  gap: 8px;
}

.allowed {
  max-width: 240px;
  white-space: normal;
}

.muted {
  color: #94a3b8;
}

.error {
  color: #dc2626;
}

.primary-btn {
  border: none;
  background: #2563eb;
  color: #fff;
  padding: 10px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 700;
}

.secondary-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0f172a;
  padding: 10px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

.link-btn {
  border: none;
  background: transparent;
  color: #2563eb;
  cursor: pointer;
  font-weight: 600;
}

.link-btn.danger {
  color: #dc2626;
}

.modal {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
}

.modal__overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
}

.modal__content {
  position: relative;
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  width: min(520px, 95vw);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  z-index: 41;
  display: grid;
  gap: 12px;
}

.modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.close-btn {
  border: none;
  background: transparent;
  font-size: 20px;
  cursor: pointer;
}

.modal__form {
  display: grid;
  gap: 12px;
}

.routes {
  display: grid;
  gap: 8px;
}

.routes__title {
  margin: 0;
  font-weight: 600;
  color: #334155;
}

.routes__list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 6px 12px;
}

.route-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #475569;
}

.modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 640px) {
  .settings__header {
    align-items: flex-start;
  }

  table {
    min-width: 100%;
  }

  th,
  td {
    padding: 10px 8px;
    font-size: 13px;
  }

  .actions {
    flex-wrap: wrap;
    gap: 6px;
  }

  .actions .link-btn {
    width: 100%;
    text-align: left;
  }

  .search-card {
    padding: 10px;
  }

  .modal__actions {
    flex-direction: column;
  }

  .modal__actions button {
    width: 100%;
  }
}
</style>

