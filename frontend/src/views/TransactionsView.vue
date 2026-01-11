<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import PeriodSelect from '@/components/PeriodSelect.vue';
import StatCard from '@/components/StatCard.vue';
import httpClient from '@/api/httpClient';
import transactionsApi from '@/api/transactions';
import PartnerDebtBalanceMeter from '@/components/PartnerDebtBalanceMeter.vue';

const periods = ref([]);
const selectedPeriodId = ref(null);
const transactions = ref([]);
const loading = ref(false);
const error = ref('');
const success = ref('');
const isMobile = ref(false);
const handleResize = () => {
  isMobile.value = window.innerWidth <= 720;
};

const selectedType = ref('all');
const selectedPerson = ref('all');
const searchText = ref('');

const showModal = ref(false);
const isEditing = ref(false);
const formError = ref('');
const saving = ref(false);
const form = ref({
  id: null,
  period_id: null,
  date: '',
  type: 'income',
  source: 'sales_general',
  amount: null,
  description: '',
});

const sourceOptions = computed(() => {
  if (form.value.type === 'income') {
    return [
      { value: 'sales_general', label: 'دخل عام' },
      { value: 'income_person1', label: 'دخل Khaled' },
      { value: 'income_person2', label: 'دخل Omar' },
    ];
  }
  if (form.value.type === 'expense') {
    return [
      { value: 'company_expense', label: 'مصاريف الشركة' },
      { value: 'expense_private_khaled', label: 'مصاريف شركة من الحساب الخاص (Khaled)' },
      { value: 'expense_private_omar', label: 'مصاريف شركة من الحساب الخاص (Omar)' },
    ];
  }
  if (form.value.type === 'partner_debt') {
    return [
      { value: 'debt_khaled_to_omar', label: 'خالد مدين لعمر' },
      { value: 'debt_omar_to_khaled', label: 'عمر مدين لخالد' },
    ];
  }
  return [
    { value: 'withdrawal_person1', label: 'سحب Khaled' },
    { value: 'withdrawal_person2', label: 'سحب Omar' },
  ];
});

const sourceLabels = {
  sales_general: 'دخل عام',
  income_person1: 'دخل Khaled',
  income_person2: 'دخل Omar',
  company_expense: 'مصاريف الشركة',
  expense_private_khaled: 'مصاريف شركة من الحساب الخاص (Khaled)',
  expense_private_omar: 'مصاريف شركة من الحساب الخاص (Omar)',
  withdrawal_person1: 'سحب Khaled',
  withdrawal_person2: 'سحب Omar',
  debt_khaled_to_omar: 'خالد مدين لعمر',
  debt_omar_to_khaled: 'عمر مدين لخالد',
};

const personFromSource = (source) => {
  if (source === 'income_person1' || source === 'withdrawal_person1') return 'Khaled';
  if (source === 'income_person2' || source === 'withdrawal_person2') return 'Omar';
  if (source === 'expense_private_khaled') return 'Khaled';
  if (source === 'expense_private_omar') return 'Omar';
  if (source === 'debt_khaled_to_omar') return 'Khaled';
  if (source === 'debt_omar_to_khaled') return 'Omar';
  return '—';
};

const formatDate = (val) => {
  if (!val) return '—';
  const d = new Date(val);
  return Number.isNaN(d.getTime()) ? val : d.toLocaleDateString();
};

const filteredTransactions = computed(() => {
  const search = searchText.value.trim().toLowerCase();
  return transactions.value.filter((t) => {
    const matchesSearch = !search || (t.description || '').toLowerCase().includes(search);
    const matchesType = selectedType.value === 'all' || t.type === selectedType.value;
    const matchesPerson =
      selectedPerson.value === 'all'
        ? true
        : selectedPerson.value === 'person1'
        ? [
            'income_person1',
            'withdrawal_person1',
            'debt_khaled_to_omar',
            'expense_private_khaled',
          ].includes(t.source)
        : [
            'income_person2',
            'withdrawal_person2',
            'debt_omar_to_khaled',
            'expense_private_omar',
          ].includes(t.source);
    return matchesSearch && matchesType && matchesPerson;
  });
});

const totals = computed(() => {
  let income = 0;
  let expenses = 0;
  let withdrawals = 0;
  let expensePrivateKhaled = 0;
  let expensePrivateOmar = 0;
  let debtKhaledToOmar = 0;
  let debtOmarToKhaled = 0;
  filteredTransactions.value.forEach((t) => {
    const amount = Number(t.amount || 0);
    if (t.type === 'income') income += amount;
    else if (t.type === 'expense') {
      expenses += amount;
      if (t.source === 'expense_private_khaled') expensePrivateKhaled += amount;
      else if (t.source === 'expense_private_omar') expensePrivateOmar += amount;
    } else if (t.type === 'withdrawal') withdrawals += amount;
    else if (t.type === 'partner_debt') {
      if (t.source === 'debt_khaled_to_omar') debtKhaledToOmar += amount;
      else if (t.source === 'debt_omar_to_khaled') debtOmarToKhaled += amount;
    }
  });
  const debtDifference = debtKhaledToOmar - debtOmarToKhaled;
  return {
    income,
    expenses,
    withdrawals,
    expensePrivateKhaled,
    expensePrivateOmar,
    debtKhaledToOmar,
    debtOmarToKhaled,
    debtNet: Math.abs(debtDifference),
    debtOwner:
      debtDifference > 0 ? 'لصالح عمر' : debtDifference < 0 ? 'لصالح خالد' : 'متعادلة',
    net: income - (expenses + withdrawals),
  };
});

const periodName = computed(() => periods.value.find((p) => p.id === selectedPeriodId.value)?.name || '');

const resetForm = () => {
  form.value = {
    id: null,
    period_id: selectedPeriodId.value || null,
    date: new Date().toISOString().slice(0, 10),
    type: 'income',
    source: 'sales_general',
    amount: null,
    description: '',
  };
  formError.value = '';
  isEditing.value = false;
};

const openCreateModal = () => {
  resetForm();
  showModal.value = true;
};

const openEditModal = (tx) => {
  form.value = {
    id: tx.id,
    period_id: tx.period_id,
    date: tx.date ? tx.date.slice(0, 10) : '',
    type: tx.type,
    source: tx.source,
    amount: tx.amount,
    description: tx.description || '',
  };
  formError.value = '';
  isEditing.value = true;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const validateForm = () => {
  if (!form.value.type || !form.value.date || !form.value.period_id) {
    formError.value = 'النوع والتاريخ والفترة مطلوبة';
    return false;
  }
  if (!form.value.amount || Number(form.value.amount) <= 0) {
    formError.value = 'يجب أن يكون المبلغ أكبر من 0';
    return false;
  }
  return true;
};

const saveTransaction = async () => {
  if (!validateForm()) return;
  saving.value = true;
  formError.value = '';
  success.value = '';
  try {
    const payload = {
      period_id: form.value.period_id,
      date: form.value.date,
      type: form.value.type,
      source: form.value.source,
      amount: form.value.amount,
      description: form.value.description,
    };

    if (isEditing.value && form.value.id) {
      await transactionsApi.updateTransaction(form.value.id, payload);
      success.value = 'تم تحديث العملية';
    } else {
      await transactionsApi.createTransaction(payload);
      success.value = 'تمت إضافة العملية';
    }
    closeModal();
    await fetchTransactions();
  } catch (err) {
    console.error(err);
    formError.value = 'تعذر حفظ العملية';
  } finally {
    saving.value = false;
  }
};

const confirmDelete = async (tx) => {
  const ok = window.confirm('هل تريد حذف العملية؟');
  if (!ok) return;
  success.value = '';
  try {
    await transactionsApi.deleteTransaction(tx.id);
    await fetchTransactions();
    success.value = 'تم حذف العملية';
  } catch (err) {
    console.error(err);
    error.value = 'تعذر حذف العملية';
  }
};

const buildParams = () => {
  const params = {};
  if (selectedPeriodId.value) params.period_id = selectedPeriodId.value;
  if (selectedType.value !== 'all') params.type = selectedType.value;

  if (selectedType.value === 'income') {
    if (selectedPerson.value === 'person1') params.source = 'income_person1';
    else if (selectedPerson.value === 'person2') params.source = 'income_person2';
  } else if (selectedType.value === 'withdrawal') {
    if (selectedPerson.value === 'person1') params.source = 'withdrawal_person1';
    else if (selectedPerson.value === 'person2') params.source = 'withdrawal_person2';
  } else if (selectedType.value === 'expense') {
    if (selectedPerson.value === 'person1') params.source = 'expense_private_khaled';
    else if (selectedPerson.value === 'person2') params.source = 'expense_private_omar';
  } else if (selectedType.value === 'partner_debt') {
    if (selectedPerson.value === 'person1') params.source = 'debt_khaled_to_omar';
    else if (selectedPerson.value === 'person2') params.source = 'debt_omar_to_khaled';
  }

  return params;
};

const fetchPeriods = async () => {
  try {
    const { data } = await httpClient.get('/periods');
    periods.value = data || [];
    if (periods.value.length > 0 && !selectedPeriodId.value) {
      selectedPeriodId.value = periods.value[0].id;
    }
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load periods';
  }
};

const fetchTransactions = async () => {
  if (!selectedPeriodId.value) return;
  loading.value = true;
  error.value = '';
  success.value = '';
  try {
    const { data } = await transactionsApi.getTransactions(buildParams());
    transactions.value = data || [];
  } catch (err) {
    console.error(err);
    error.value = 'تعذر تحميل العمليات';
  } finally {
    loading.value = false;
  }
};

watch([selectedPeriodId, selectedType, selectedPerson], () => {
  fetchTransactions();
});

onMounted(async () => {
  handleResize();
  window.addEventListener('resize', handleResize);
  await fetchPeriods();
  await fetchTransactions();
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
});
</script>

<template>
  <section class="transactions">
    <header class="transactions__header">
      <div class="title">
        <h1 class="page-title">Transactions</h1>
        <p class="subtitle text-muted">{{ periodName }}</p>
      </div>
      <button class="primary-btn btn-responsive" type="button" @click="openCreateModal">
        + إضافة عملية
      </button>
    </header>

    <p v-if="success" class="success">{{ success }}</p>
    <p v-if="error" class="error">{{ error }}</p>

    <details class="section-toggle">
      <summary class="section-toggle__summary">
        <span>الإحصائيات والبطاقات</span>
      </summary>
      <div class="section-toggle__content">
        <div class="card stat-card__container">
      <div class="stat-grid">
        <StatCard
          title="مصاريف شركة من الحساب الخاص (Khaled)"
          :value="totals.expensePrivateKhaled"
          variant="expense"
        />
        <StatCard
          title="مصاريف شركة من الحساب الخاص (Omar)"
          :value="totals.expensePrivateOmar"
          variant="expense"
        />
      </div>
    </div>

    <div class="insights">
      <div class="card meter-card">
        <div class="meter-card__header">
          <p class="meter-card__title">مؤشر التوازن بين الديون</p>
          <p class="text-muted meter-card__subtitle">شريط واحد يوضح أي كفة أرجح حالياً</p>
        </div>
        <PartnerDebtBalanceMeter
          :khaled-to-omar="totals.debtKhaledToOmar || 0"
          :omar-to-khaled="totals.debtOmarToKhaled || 0"
        />
      </div>

      <div class="card stat-card__container">
        <div class="stat-grid">
          <StatCard title="إجمالي الدخل" :value="totals.income" variant="income" />
          <StatCard title="إجمالي المصاريف" :value="totals.expenses" variant="expense" />
          <StatCard title="إجمالي السحب" :value="totals.withdrawals" variant="expense" />
          <StatCard title="الصافي" :value="totals.net" variant="summary" />
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
      <PeriodSelect v-model="selectedPeriodId" :periods="periods" label="Period" />

      <label class="filter">
        <span>النوع</span>
        <select v-model="selectedType">
          <option value="all">الكل</option>
          <option value="income">دخل</option>
          <option value="expense">مصاريف</option>
          <option value="withdrawal">سحب</option>
          <option value="partner_debt">ديون بين الشريكين</option>
        </select>
      </label>

      <label class="filter">
        <span>الشخص</span>
        <select v-model="selectedPerson">
          <option value="all">الكل</option>
          <option value="person1">Khaled</option>
          <option value="person2">Omar</option>
        </select>
      </label>

      <label class="filter filter--grow">
        <span>بحث</span>
        <input v-model="searchText" type="text" placeholder="الوصف..." />
      </label>
        </div>
      </div>
    </details>

    <div class="table-card" v-if="!isMobile">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>النوع</th>
              <th>الشخص</th>
              <th>الوصف</th>
              <th class="align-right">المبلغ</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6">...جاري التحميل</td>
            </tr>
            <tr v-else-if="error">
              <td colspan="6" class="error">{{ error }}</td>
            </tr>
            <tr v-else-if="!filteredTransactions.length">
              <td colspan="6" class="muted">لا توجد عمليات لهذه الفترة.</td>
            </tr>
            <tr v-for="tx in filteredTransactions" :key="tx.id">
              <td>{{ formatDate(tx.date) }}</td>
              <td>{{ sourceLabels[tx.source] || tx.type }}</td>
              <td>{{ personFromSource(tx.source) }}</td>
              <td>{{ tx.description || '—' }}</td>
              <td class="align-right">{{ Number(tx.amount).toLocaleString() }}</td>
              <td class="actions">
                <button class="link-btn" type="button" @click="openEditModal(tx)">تعديل</button>
                <button class="link-btn danger" type="button" @click="confirmDelete(tx)">
                  حذف
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card tx-cards" v-else>
      <p v-if="loading" class="muted">...جاري التحميل</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!filteredTransactions.length" class="muted">لا توجد عمليات لهذه الفترة.</p>
      <article v-else v-for="tx in filteredTransactions" :key="tx.id" class="tx-card">
        <div class="tx-card__row">
          <span class="tx-card__label">التاريخ</span>
          <span class="tx-card__value">{{ formatDate(tx.date) }}</span>
        </div>
        <div class="tx-card__row">
          <span class="tx-card__label">النوع</span>
          <span class="tx-card__value">{{ sourceLabels[tx.source] || tx.type }}</span>
        </div>
        <div class="tx-card__row">
          <span class="tx-card__label">الشخص</span>
          <span class="tx-card__value">{{ personFromSource(tx.source) }}</span>
        </div>
        <div class="tx-card__row">
          <span class="tx-card__label">الوصف</span>
          <span class="tx-card__value">{{ tx.description || '—' }}</span>
        </div>
        <div class="tx-card__row">
          <span class="tx-card__label">المبلغ</span>
          <span class="tx-card__value tx-card__value--strong">{{ Number(tx.amount).toLocaleString() }}</span>
        </div>
        <div class="tx-card__actions">
          <button class="secondary-btn" type="button" @click="openEditModal(tx)">تعديل</button>
          <button class="secondary-btn danger-outline" type="button" @click="confirmDelete(tx)">
            حذف
          </button>
        </div>
      </article>
    </div>

    <div v-if="showModal" class="modal">
      <div class="modal__overlay" @click="closeModal"></div>
      <div class="modal__content">
        <div class="modal__header">
          <h2>{{ isEditing ? 'تعديل عملية' : 'إضافة عملية' }}</h2>
          <button class="close-btn" type="button" @click="closeModal">×</button>
        </div>
        <form class="modal__form" @submit.prevent="saveTransaction">
          <div class="form-grid" :class="{ 'form-grid--single': isMobile }">
            <label>
              الفترة
              <select v-model.number="form.period_id" required>
                <option v-for="p in periods" :key="p.id" :value="p.id">
                  {{ p.name }}
                </option>
              </select>
            </label>

            <label>
              التاريخ
              <input v-model="form.date" type="date" required />
            </label>

            <label>
              النوع
              <select
                v-model="form.type"
                required
                @change="form.source = sourceOptions[0]?.value || ''"
              >
                <option value="income">دخل</option>
                <option value="expense">مصاريف</option>
                <option value="withdrawal">سحب</option>
                <option value="partner_debt">ديون بين الشريكين</option>
              </select>
            </label>

            <label>
              التصنيف
              <select v-model="form.source" required>
                <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </label>

            <label>
              المبلغ
              <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required />
            </label>
          </div>

          <label>
            الوصف
            <textarea v-model="form.description" rows="3" placeholder="اختياري"></textarea>
          </label>

          <p v-if="formError" class="error">{{ formError }}</p>

          <div class="modal__actions">
            <button class="secondary-btn" type="button" @click="closeModal">إلغاء</button>
            <button class="primary-btn btn-responsive" type="submit" :disabled="saving">
              {{ saving ? 'جاري الحفظ...' : 'حفظ' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<style scoped>
.transactions {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.transactions__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.title h1 {
  margin: 0;
}

.subtitle {
  margin: 4px 0 0;
  color: #64748b;
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

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  align-items: end;
}

.insights {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
  align-items: stretch;
}

.meter-card {
  display: grid;
  gap: 8px;
}

.meter-card__header {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meter-card__title {
  margin: 0;
  font-weight: 800;
  color: #0f172a;
}

.meter-card__subtitle {
  margin: 0;
  font-size: 13px;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.tx-cards {
  display: grid;
  gap: 10px;
}

.tx-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
  background: #fff;
  display: grid;
  gap: 8px;
}

.tx-card__row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
  color: #0f172a;
}

.tx-card__label {
  color: #475569;
}

.tx-card__value {
  font-weight: 600;
}

.tx-card__value--strong {
  font-size: 16px;
}

.tx-card__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.secondary-btn.danger-outline {
  border-color: #ef4444;
  color: #b91c1c;
}

.filter {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 14px;
  color: #475569;
}

.filter select,
.filter input {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
}

.stat-card__container .stat-grid {
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.filter--grow {
  grid-column: span 2;
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

.success {
  color: #16a34a;
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

.totals {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.modal {
  position: fixed;
  inset: 0;
  z-index: 30;
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
  width: min(640px, 95vw);
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  z-index: 31;
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

.modal__form label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 14px;
  color: #475569;
}

.modal__form input,
.modal__form select,
.modal__form textarea {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
}

.modal__form textarea {
  resize: vertical;
}

.modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.form-grid--single {
  grid-template-columns: 1fr;
}

.modal__actions button {
  width: auto;
}

@media (max-width: 640px) {
  .modal__actions {
    flex-direction: column;
  }

  .modal__actions button {
    width: 100%;
  }
}

@media (max-width: 600px) {
  .filter--grow {
    grid-column: span 1;
  }

  table {
    min-width: 600px;
  }
}
</style>
