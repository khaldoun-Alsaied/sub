<script setup>
import { ref, onMounted } from 'vue';
import httpClient from '@/api/httpClient';

const categories = ref([]);
const paymentMethods = ref([]);
const settings = ref(null);
const loading = ref(false);
const error = ref('');

const newCategory = ref({ name: '', sort_order: 0, is_active: 1 });
const newMethod = ref({ name: '', sort_order: 0, is_active: 1 });

async function fetchAll() {
  loading.value = true;
  error.value = '';
  try {
    const [catRes, pmRes, setRes] = await Promise.all([
      httpClient.get('/expense-categories'),
      httpClient.get('/payment-methods'),
      httpClient.get('/expense-settings'),
    ]);
    categories.value = catRes.data || [];
    paymentMethods.value = pmRes.data || [];
    settings.value = setRes.data || {};
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحميل الإعدادات';
  } finally {
    loading.value = false;
  }
}

async function addCategory() {
  if (!newCategory.value.name) return;
  try {
    await httpClient.post('/expense-categories', newCategory.value);
    newCategory.value = { name: '', sort_order: 0, is_active: 1 };
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر إضافة التصنيف';
  }
}

async function addMethod() {
  if (!newMethod.value.name) return;
  try {
    await httpClient.post('/payment-methods', newMethod.value);
    newMethod.value = { name: '', sort_order: 0, is_active: 1 };
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر إضافة طريقة الدفع';
  }
}

async function toggleCategory(cat) {
  try {
    await httpClient.patch(`/expense-categories/${cat.id}`, { is_active: cat.is_active ? 0 : 1 });
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحديث التصنيف';
  }
}

async function toggleMethod(pm) {
  try {
    await httpClient.patch(`/payment-methods/${pm.id}`, { is_active: pm.is_active ? 0 : 1 });
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحديث طريقة الدفع';
  }
}

async function deleteCategory(id) {
  if (!window.confirm('حذف التصنيف؟')) return;
  try {
    await httpClient.delete(`/expense-categories/${id}`);
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر حذف التصنيف';
  }
}

async function deleteMethod(id) {
  if (!window.confirm('حذف طريقة الدفع؟')) return;
  try {
    await httpClient.delete(`/payment-methods/${id}`);
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر حذف طريقة الدفع';
  }
}

async function saveSettings() {
  if (!settings.value) return;
  try {
    await httpClient.patch('/expense-settings', settings.value);
    await fetchAll();
  } catch (err) {
    console.error(err);
    error.value = 'تعذر حفظ إعدادات المصاريف';
  }
}

onMounted(fetchAll);
</script>

<template>
  <section class="expenses-settings">
    <header class="settings__header">
      <div>
        <h1 class="page-title">إعدادات المصاريف</h1>
        <p class="text-muted">إدارة التصنيفات، طرق الدفع، والإعدادات العامة.</p>
      </div>
    </header>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="loading" class="muted">...جاري التحميل</p>

    <div class="grid">
      <div class="card">
        <h3>التصنيفات</h3>
        <div class="form-row">
          <input v-model="newCategory.name" type="text" placeholder="اسم التصنيف" />
          <input v-model.number="newCategory.sort_order" type="number" placeholder="الترتيب" />
          <button class="primary-btn" type="button" @click="addCategory">إضافة</button>
        </div>
        <ul class="list">
          <li v-for="cat in categories" :key="cat.id">
            <span>{{ cat.name }} (ترتيب: {{ cat.sort_order }})</span>
            <div class="actions">
              <button class="link-btn" type="button" @click="toggleCategory(cat)">
                {{ cat.is_active ? 'تعطيل' : 'تفعيل' }}
              </button>
              <button class="link-btn danger" type="button" @click="deleteCategory(cat.id)">حذف</button>
            </div>
          </li>
        </ul>
      </div>

      <div class="card">
        <h3>طرق الدفع</h3>
        <div class="form-row">
          <input v-model="newMethod.name" type="text" placeholder="اسم الطريقة" />
          <input v-model.number="newMethod.sort_order" type="number" placeholder="الترتيب" />
          <button class="primary-btn" type="button" @click="addMethod">إضافة</button>
        </div>
        <ul class="list">
          <li v-for="pm in paymentMethods" :key="pm.id">
            <span>{{ pm.name }} (ترتيب: {{ pm.sort_order }})</span>
            <div class="actions">
              <button class="link-btn" type="button" @click="toggleMethod(pm)">
                {{ pm.is_active ? 'تعطيل' : 'تفعيل' }}
              </button>
              <button class="link-btn danger" type="button" @click="deleteMethod(pm.id)">حذف</button>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <div class="card">
      <h3>إعدادات عامة</h3>
      <div class="settings-grid" v-if="settings">
        <label>
          <input type="checkbox" v-model="settings.require_description" :true-value="1" :false-value="0" />
          إلزام الوصف
        </label>
        <label>
          <input type="checkbox" v-model="settings.enable_attachments" :true-value="1" :false-value="0" />
          السماح بالمرفقات
        </label>
        <label>
          حد أقصى للمبلغ (اختياري)
          <input v-model.number="settings.max_amount" type="number" step="0.01" min="0" />
        </label>
        <label>
          التصنيف الافتراضي
          <select v-model="settings.default_category_id">
            <option :value="null">بدون</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </label>
      </div>
      <div class="actions-row">
        <button class="primary-btn" type="button" @click="saveSettings">حفظ الإعدادات</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.expenses-settings {
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

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
}

.card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  display: grid;
  gap: 12px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 120px auto;
  gap: 8px;
}

.list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}

.list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.actions {
  display: flex;
  gap: 8px;
}

.settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.actions-row {
  display: flex;
  justify-content: flex-end;
}

.error {
  color: #dc2626;
}

.muted {
  color: #94a3b8;
}

input,
select {
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
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

@media (max-width: 640px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .list li {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }

  .actions {
    justify-content: flex-start;
  }
}
</style>
