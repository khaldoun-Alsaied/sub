<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import activityLogsApi from '@/api/activityLogs';

const logs = ref([]);
const loading = ref(false);
const error = ref(null);
const success = ref('');
const isMobile = ref(false);

const handleResize = () => {
  isMobile.value = window.innerWidth <= 720;
};

const page = ref(1);
const limit = ref(20);
const total = ref(0);

const filters = ref({
  action: 'all',
  user_id: '',
  date_from: '',
  date_to: '',
});

const formatDate = (val) => {
  if (!val) return '—';
  const d = new Date(val);
  return Number.isNaN(d.getTime()) ? val : d.toLocaleDateString();
};

const actionOptions = [
  'LOGIN',
  'CREATE_TRANSACTION',
  'UPDATE_TRANSACTION',
  'DELETE_TRANSACTION',
  'UPDATE_MANUAL_BALANCE',
];

const fetchLogs = async () => {
  loading.value = true;
  error.value = null;
  success.value = '';
  try {
    const params = {
      page: page.value,
      limit: limit.value,
    };
    if (filters.value.action && filters.value.action !== 'all') {
      params.action = filters.value.action;
    }
    if (filters.value.user_id) {
      params.user_id = filters.value.user_id;
    }
    if (filters.value.date_from) {
      params.date_from = filters.value.date_from;
    }
    if (filters.value.date_to) {
      params.date_to = filters.value.date_to;
    }

    const { data } = await activityLogsApi.getActivityLogs(params);
    logs.value = data?.data || [];
    total.value = data?.pagination?.total || 0;
    page.value = data?.pagination?.page || 1;
    limit.value = data?.pagination?.limit || limit.value;
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحميل سجل النشاط';
  } finally {
    loading.value = false;
  }
};

const handleFilterChange = () => {
  page.value = 1;
  fetchLogs();
};

const nextPage = () => {
  if (page.value * limit.value >= total.value) return;
  page.value += 1;
  fetchLogs();
};

const prevPage = () => {
  if (page.value <= 1) return;
  page.value -= 1;
  fetchLogs();
};

watch(
  () => [filters.value.action, filters.value.user_id, filters.value.date_from, filters.value.date_to],
  () => handleFilterChange()
);

onMounted(() => {
  handleResize();
  window.addEventListener('resize', handleResize);
  fetchLogs();
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
});
</script>

<template>
  <section class="activity-log">
    <header class="header">
      <h1>Activity Log</h1>
      <p class="subtitle">سجل النشاطات للنظام</p>
    </header>

    <p v-if="success" class="success">{{ success }}</p>
    <div class="filters">
      <label class="filter">
        <span>الإجراء</span>
        <select v-model="filters.action">
          <option value="all">الكل</option>
          <option v-for="act in actionOptions" :key="act" :value="act">
            {{ act }}
          </option>
        </select>
      </label>

      <label class="filter">
        <span>المستخدم (ID)</span>
        <input v-model="filters.user_id" type="text" placeholder="User ID" />
      </label>

      <label class="filter">
        <span>من تاريخ</span>
        <input v-model="filters.date_from" type="date" />
      </label>

      <label class="filter">
        <span>إلى تاريخ</span>
        <input v-model="filters.date_to" type="date" />
      </label>
    </div>

    <div class="table-card" v-if="!isMobile">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>المستخدم</th>
              <th>الإجراء</th>
              <th>الوصف</th>
              <th>الكيان</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5">...جاري التحميل</td>
            </tr>
            <tr v-else-if="error">
              <td colspan="5" class="error">{{ error }}</td>
            </tr>
            <tr v-else-if="!logs.length">
              <td colspan="5" class="muted">No activity recorded yet.</td>
            </tr>
            <tr v-for="log in logs" :key="log.id">
              <td>{{ formatDate(log.created_at) }}</td>
              <td>{{ log.user_id || '—' }}</td>
              <td>{{ log.action }}</td>
              <td>{{ log.description || '—' }}</td>
              <td>
                {{ log.entity_type || '—' }} <span v-if="log.entity_id">#{{ log.entity_id }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="pagination">
        <button class="secondary-btn" type="button" :disabled="page === 1" @click="prevPage">
          السابق
        </button>
        <span>الصفحة {{ page }}</span>
        <button
          class="secondary-btn"
          type="button"
          :disabled="page * limit >= total"
          @click="nextPage"
        >
          التالي
        </button>
      </div>
    </div>

    <div class="mobile-list card" v-else>
      <p v-if="loading" class="muted">...جاري التحميل</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!logs.length" class="muted">لا توجد نشاطات بعد.</p>
      <article v-else v-for="log in logs" :key="log.id" class="mobile-card">
        <div class="mobile-row">
          <span class="mobile-label">التاريخ</span>
          <span class="mobile-value">{{ formatDate(log.created_at) }}</span>
        </div>
        <div class="mobile-row">
          <span class="mobile-label">المستخدم</span>
          <span class="mobile-value">{{ log.user_id || '—' }}</span>
        </div>
        <div class="mobile-row">
          <span class="mobile-label">الإجراء</span>
          <span class="mobile-value">{{ log.action }}</span>
        </div>
        <div class="mobile-row">
          <span class="mobile-label">الوصف</span>
          <span class="mobile-value">{{ log.description || '—' }}</span>
        </div>
        <div class="mobile-row">
          <span class="mobile-label">الكيان</span>
          <span class="mobile-value">
            {{ log.entity_type || '—' }} <span v-if="log.entity_id">#{{ log.entity_id }}</span>
          </span>
        </div>
      </article>
      <div class="pagination">
        <button class="secondary-btn" type="button" :disabled="page === 1" @click="prevPage">
          السابق
        </button>
        <span>الصفحة {{ page }}</span>
        <button
          class="secondary-btn"
          type="button"
          :disabled="page * limit >= total"
          @click="nextPage"
        >
          التالي
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.activity-log {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.subtitle {
  margin: 0;
  color: #64748b;
}

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 14px;
  color: #475569;
}

.filter input,
.filter select {
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
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}

.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 700px;
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

tbody tr:nth-child(even) {
  background: #f8fafc;
}

.muted {
  color: #94a3b8;
}

.error {
  color: #dc2626;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
}

.secondary-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0f172a;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

.mobile-list {
  display: grid;
  gap: 10px;
}

.mobile-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
  background: #fff;
  display: grid;
  gap: 8px;
}

.mobile-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}

.mobile-label {
  color: #475569;
}

.mobile-value {
  font-weight: 600;
  color: #0f172a;
}
</style>
