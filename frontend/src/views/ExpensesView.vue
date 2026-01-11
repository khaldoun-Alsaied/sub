<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import httpClient from '@/api/httpClient';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const isViewer = computed(() => authStore.user?.role === 'viewer');

const expenses = ref([]);
const categories = ref([]);
const paymentMethods = ref([]);
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);
const updateIsMobile = () => {
  if (typeof window !== 'undefined') {
    isMobile.value = window.innerWidth < 640;
  }
};

const filters = ref({
  dateFrom: '',
  dateTo: '',
  categoryId: '',
  paymentMethodId: '',
  search: '',
});

const loading = ref(false);
const error = ref('');

const modalOpen = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const form = ref({
  id: null,
  date: new Date().toISOString().slice(0, 10),
  amount: null,
  category_id: '',
  payment_method_id: '',
  description: '',
  attachment_url: '',
});

const stats = computed(() => {
  const total = expenses.value.reduce((s, e) => s + Number(e.amount || 0), 0);
  const todayStr = new Date().toISOString().slice(0, 10);
  const thisMonth = new Date().toISOString().slice(0, 7); // YYYY-MM
  const totalToday = expenses.value
    .filter((e) => e.date === todayStr)
    .reduce((s, e) => s + Number(e.amount || 0), 0);
  const totalMonth = expenses.value
    .filter((e) => (e.date || '').startsWith(thisMonth))
    .reduce((s, e) => s + Number(e.amount || 0), 0);
  return { total, totalToday, totalMonth };
});

const categoryTotals = computed(() => {
  const totalsMap = expenses.value.reduce((acc, exp) => {
    const id = exp.category_id;
    const amt = Number(exp.amount || 0);
    acc[id] = (acc[id] || 0) + amt;
    return acc;
  }, {});
  return categories.value.map((cat) => ({
    id: cat.id,
    name: cat.name,
    total: totalsMap[cat.id] || 0,
  }));
});

const filteredExpenses = computed(() => expenses.value); // already filtered via API params

async function fetchLookups() {
  try {
    const [catsRes, methodsRes] = await Promise.all([
      httpClient.get('/expense-categories'),
      httpClient.get('/payment-methods'),
    ]);
    categories.value = catsRes.data || [];
    paymentMethods.value = methodsRes.data || [];
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحميل التصنيفات أو طرق الدفع';
  }
}

async function fetchExpenses() {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (filters.value.dateFrom) params.date_from = filters.value.dateFrom;
    if (filters.value.dateTo) params.date_to = filters.value.dateTo;
    if (filters.value.categoryId) params.category_id = filters.value.categoryId;
    if (filters.value.paymentMethodId) params.payment_method_id = filters.value.paymentMethodId;
    if (filters.value.search) params.q = filters.value.search;
    const { data } = await httpClient.get('/expenses', { params });
    expenses.value = data || [];
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحميل المصاريف';
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  form.value = {
    id: null,
    date: new Date().toISOString().slice(0, 10),
    amount: null,
    category_id: categories.value[0]?.id || '',
    payment_method_id: paymentMethods.value[0]?.id || '',
    description: '',
    attachment_url: '',
  };
  isEditing.value = false;
}

function openCreate() {
  resetForm();
  modalOpen.value = true;
}

function openEdit(expense) {
  form.value = {
    id: expense.id,
    date: expense.date,
    amount: Number(expense.amount),
    category_id: expense.category_id,
    payment_method_id: expense.payment_method_id,
    description: expense.description || '',
    attachment_url: expense.attachment_url || '',
  };
  isEditing.value = true;
  modalOpen.value = true;
}

async function saveExpense() {
  saving.value = true;
  error.value = '';
  try {
    const payload = {
      date: form.value.date,
      amount: form.value.amount,
      category_id: form.value.category_id,
      payment_method_id: form.value.payment_method_id,
      description: form.value.description || null,
      attachment_url: form.value.attachment_url || null,
    };
    if (isEditing.value) {
      await httpClient.patch(`/expenses/${form.value.id}`, payload);
    } else {
      await httpClient.post('/expenses', payload);
    }
    modalOpen.value = false;
    await fetchExpenses();
  } catch (err) {
    console.error(err);
    error.value = err?.response?.data?.error || 'تعذر حفظ المصروف';
  } finally {
    saving.value = false;
  }
}

async function deleteExpense(id) {
  if (!window.confirm('هل تريد حذف المصروف؟')) return;
  error.value = '';
  try {
    await httpClient.delete(`/expenses/${id}`);
    await fetchExpenses();
  } catch (err) {
    console.error(err);
    error.value = err?.response?.data?.error || 'تعذر حذف المصروف';
  }
}

onMounted(async () => {
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateIsMobile);
    updateIsMobile();
  }
  await fetchLookups();
  await fetchExpenses();
});

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateIsMobile);
  }
});

watch(filters, fetchExpenses, { deep: true });
</script>

<template>
  <section class="expenses">
    <header class="expenses__header">
      <div>
        <h1 class="page-title">المصاريف</h1>
        <p class="text-muted">إدارة وإدخال مصاريف الشركة</p>
      </div>
      <button class="primary-btn" type="button" @click="openCreate" :disabled="isViewer">+ إضافة مصروف</button>
    </header>

    <details class="section-toggle">
      <summary class="section-toggle__summary">
        <span>الإحصائيات والبطاقات</span>
      </summary>
      <div class="section-toggle__content">
        <div class="cards-grid">
          <div class="card stat-card">
            <p class="stat-card__label">إجمالي المصاريف</p>
            <p class="stat-card__value">{{ stats.total.toLocaleString() }}</p>
          </div>
          <div class="card stat-card">
            <p class="stat-card__label">مصاريف اليوم</p>
            <p class="stat-card__value">{{ stats.totalToday.toLocaleString() }}</p>
          </div>
          <div class="card stat-card">
            <p class="stat-card__label">مصاريف هذا الشهر</p>
            <p class="stat-card__value">{{ stats.totalMonth.toLocaleString() }}</p>
          </div>
        </div>

        <div class="card category-totals">
          <div class="category-totals__header">
            <p class="category-totals__title">مجموع المصاريف لكل تصنيف</p>
          </div>
          <div class="category-totals__list">
            <div v-for="cat in categoryTotals" :key="cat.id" class="category-chip">
              <span class="category-chip__name">{{ cat.name }}</span>
              <span class="category-chip__value">{{ cat.total.toLocaleString() }}</span>
            </div>
          </div>
        </div>
      </div>
    </details>

    <details class="section-toggle">
      <summary class="section-toggle__summary">
        <span>البحث والتصفية</span>
      </summary>
      <div class="section-toggle__content">
        <div class="filters card">
          <label class="filter">
            <span>من</span>
            <input v-model="filters.dateFrom" type="date" />
          </label>
          <label class="filter">
            <span>إلى</span>
            <input v-model="filters.dateTo" type="date" />
          </label>
          <label class="filter">
            <span>التصنيف</span>
            <select v-model="filters.categoryId">
              <option value="">الكل</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </label>
          <label class="filter">
            <span>طريقة الدفع</span>
            <select v-model="filters.paymentMethodId">
              <option value="">الكل</option>
              <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
            </select>
          </label>
          <label class="filter filter--grow">
            <span>بحث</span>
            <input v-model="filters.search" type="text" placeholder="الوصف..." />
          </label>
        </div>
      </div>
    </details>

    <p v-if="error" class="error">{{ error }}</p>

    <div v-if="isMobile" class="mobile-list">
      <article v-for="exp in filteredExpenses" :key="exp.id" class="mobile-card">
        <header class="mobile-card__header">
          <div>
            <p class="mobile-card__date">{{ exp.date }}</p>
            <p class="mobile-card__meta">
              {{ categories.find((c) => c.id === exp.category_id)?.name || 'N/A' }} -
              {{ paymentMethods.find((m) => m.id === exp.payment_method_id)?.name || 'N/A' }}
            </p>
          </div>
          <span class="mobile-card__amount">{{ Number(exp.amount).toLocaleString() }}</span>
        </header>
        <p class="mobile-card__desc">{{ exp.description || 'N/A' }}</p>
        <footer class="mobile-card__actions">
          <button class="link-btn" type="button" @click="openEdit(exp)" :disabled="isViewer">تعديل</button>
          <button class="link-btn danger" type="button" @click="deleteExpense(exp.id)" :disabled="isViewer">
            حذف
          </button>
        </footer>
      </article>
    </div>

    <div v-else class="table-card">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>التصنيف</th>
              <th>طريقة الدفع</th>
              <th>الوصف</th>
              <th class="align-right">المبلغ</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="muted">...جاري التحميل</td>
            </tr>
            <tr v-else-if="!filteredExpenses.length">
              <td colspan="6" class="muted">لا توجد مصاريف.</td>
            </tr>
            <tr v-else v-for="exp in filteredExpenses" :key="exp.id">
              <td>{{ exp.date }}</td>
              <td>{{ categories.find((c) => c.id === exp.category_id)?.name || '—' }}</td>
              <td>{{ paymentMethods.find((m) => m.id === exp.payment_method_id)?.name || '—' }}</td>
              <td>{{ exp.description || '—' }}</td>
              <td class="align-right">{{ Number(exp.amount).toLocaleString() }}</td>
              <td class="actions">
                <button class="link-btn" type="button" @click="openEdit(exp)" :disabled="isViewer">تعديل</button>
                <button class="link-btn danger" type="button" @click="deleteExpense(exp.id)" :disabled="isViewer">
                  حذف
                </button>
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
          <h2>{{ isEditing ? 'تعديل مصروف' : 'إضافة مصروف' }}</h2>
          <button class="close-btn" type="button" @click="modalOpen = false">×</button>
        </div>
        <form class="modal__form" @submit.prevent="saveExpense">
          <div class="form-grid">
            <label>
              التاريخ
              <input v-model="form.date" type="date" required />
            </label>
            <label>
              المبلغ
              <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required />
            </label>
            <label>
              التصنيف
              <select v-model="form.category_id" required>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </label>
            <label>
              طريقة الدفع
              <select v-model="form.payment_method_id" required>
                <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
              </select>
            </label>
          </div>
          <label>
            الوصف
            <textarea v-model="form.description" rows="3" placeholder="وصف مختصر"></textarea>
          </label>
          <label>
            رابط مرفق (اختياري)
            <input v-model="form.attachment_url" type="url" placeholder="https://..." />
          </label>

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
.expenses {
  display: grid;
  gap: 16px;
  padding-bottom: 12px;
}

.expenses__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.section-toggle {
  display: grid;
  gap: 12px;
}

.section-toggle__summary {
  list-style: none;
  cursor: pointer;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-weight: 700;
  color: #0f172a;
}

.section-toggle__summary::-webkit-details-marker {
  display: none;
}

.section-toggle__summary::marker {
  content: '';
}

.section-toggle__summary:focus-visible {
  outline: 2px solid var(--color-primary-light);
  outline-offset: 2px;
}

.section-toggle__summary::after {
  content: '';
  width: 8px;
  height: 8px;
  border-right: 2px solid #64748b;
  border-bottom: 2px solid #64748b;
  transform: rotate(-45deg);
  transition: transform 0.2s ease;
}

.section-toggle[open] > .section-toggle__summary::after {
  transform: rotate(45deg);
}

.section-toggle__content {
  display: grid;
  gap: 16px;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.stat-card {
  padding: 12px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}

.stat-card__label {
  margin: 0;
  color: #475569;
  font-size: 14px;
}

.stat-card__value {
  margin: 4px 0 0;
  font-weight: 800;
  font-size: 20px;
}

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  align-items: end;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
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

.filter--grow {
  grid-column: span 2;
}

.category-totals {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
  display: grid;
  gap: 10px;
}

.category-totals__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.category-totals__title {
  margin: 0;
  font-weight: 700;
  color: #0f172a;
}

.category-totals__list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.category-chip {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.category-chip__name {
  font-weight: 600;
  color: #334155;
}

.category-chip__value {
  font-weight: 700;
  color: #0f172a;
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
  min-width: 720px;
}

.mobile-list {
  display: grid;
  gap: 10px;
}

.mobile-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  display: grid;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.mobile-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  flex-wrap: wrap;
}

.mobile-card__date {
  margin: 0;
  font-weight: 700;
  color: #0f172a;
}

.mobile-card__meta {
  margin: 2px 0 0;
  color: #475569;
  font-size: 13px;
}

.mobile-card__amount {
  font-weight: 800;
  color: #0f172a;
}

.mobile-card__desc {
  margin: 4px 0 0;
  color: #0f172a;
}

.mobile-card__actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
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

.align-right {
  text-align: right;
}

.actions {
  display: flex;
  gap: 8px;
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
  width: min(600px, 95vw);
  max-height: 70vh;
  overflow-y: auto;
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.modal__form textarea {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  resize: vertical;
}

.modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 640px) {
  .filter--grow {
    grid-column: span 1;
  }

  .modal__actions {
    flex-direction: column;
  }

  .modal__actions button {
    width: 100%;
  }

  .cards-grid {
    grid-template-columns: 1fr;
  }

  .category-totals__list {
    grid-template-columns: 1fr;
  }

  table {
    min-width: 100%;
  }

  .modal {
    align-items: flex-start;
    padding: 8px;
  }

  .modal__content {
    width: 100%;
    padding: 14px;
    max-height: 65vh;
  }
}
</style>
