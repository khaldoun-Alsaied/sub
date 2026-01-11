<script setup>
import { ref, onMounted, watch, computed, onBeforeUnmount } from 'vue';
import PeriodSelect from '@/components/PeriodSelect.vue';
import StatCard from '@/components/StatCard.vue';
import manualBalancesApi from '@/api/manualBalances';
import httpClient from '@/api/httpClient';

const periods = ref([]);
const selectedPeriodId = ref(null);

const currentBalance = ref(null);
const lastUpdatedAt = ref(null);
const loadingCurrent = ref(false);
const errorCurrent = ref(null);

const form = ref({
  manual_company_balance: null,
  note: '',
});
const saving = ref(false);
const formMessage = ref('');
const errorMessage = ref('');
const isMobile = ref(false);

const history = ref([]);
const loadingHistory = ref(false);
const errorHistory = ref(null);

const hasHistoryEndpoint = false; // backend only returns latest; history placeholder shown

const periodName = computed(() => {
  const found = periods.value.find((p) => p.id === selectedPeriodId.value);
  return found?.name || '';
});

const fetchPeriods = async () => {
  try {
    const { data } = await httpClient.get('/periods');
    periods.value = data || [];
    if (periods.value.length > 0 && !selectedPeriodId.value) {
      selectedPeriodId.value = periods.value[0].id;
    }
  } catch (err) {
    console.error(err);
    errorCurrent.value = 'Failed to load periods';
  }
};

const loadCurrentBalance = async () => {
  if (!selectedPeriodId.value) return;
  loadingCurrent.value = true;
  errorCurrent.value = null;
  try {
    const { data } = await manualBalancesApi.getManualBalances({
      period_id: selectedPeriodId.value,
    });
    const item = Array.isArray(data) ? data[0] : data;
    currentBalance.value = item || null;
    lastUpdatedAt.value = item ? item.updated_at || item.created_at : null;
    if (item) {
      form.value.manual_company_balance = item.manual_company_balance;
    } else {
      form.value.manual_company_balance = null;
    }
  } catch (err) {
    console.error(err);
    errorCurrent.value = 'Failed to load manual balance';
  } finally {
    loadingCurrent.value = false;
  }
};

const loadHistory = async () => {
  if (!hasHistoryEndpoint) return;
  if (!selectedPeriodId.value) return;
  loadingHistory.value = true;
  errorHistory.value = null;
  try {
    const { data } = await manualBalancesApi.getManualBalances({
      period_id: selectedPeriodId.value,
    });
    history.value = Array.isArray(data) ? data : data ? [data] : [];
  } catch (err) {
    console.error(err);
    errorHistory.value = 'Failed to load history';
  } finally {
    loadingHistory.value = false;
  }
};

const validateForm = () => {
  if (
    form.value.manual_company_balance === null ||
    form.value.manual_company_balance === '' ||
    Number.isNaN(Number(form.value.manual_company_balance))
  ) {
    formMessage.value = 'الرصيد اليدوي مطلوب';
    return false;
  }
  return true;
};

const handleResize = () => {
  isMobile.value = window.innerWidth <= 640;
};

const saveManualBalance = async () => {
  if (!selectedPeriodId.value) return;
  if (!validateForm()) return;

  saving.value = true;
  formMessage.value = '';
  errorMessage.value = '';
  try {
    await manualBalancesApi.createManualBalance({
      period_id: selectedPeriodId.value,
      manual_company_balance: form.value.manual_company_balance,
      note: form.value.note,
    });
    formMessage.value = 'تم الحفظ بنجاح';
    form.value.note = '';
    await loadCurrentBalance();
    await loadHistory();
  } catch (err) {
    console.error(err);
    formMessage.value = 'تعذر حفظ الرصيد اليدوي';
    errorMessage.value = 'تعذر حفظ الرصيد اليدوي';
  } finally {
    saving.value = false;
  }
};

watch(
  () => selectedPeriodId.value,
  (newVal, oldVal) => {
    if (newVal && newVal !== oldVal) {
      loadCurrentBalance();
      loadHistory();
    }
  }
);

onMounted(async () => {
  handleResize();
  window.addEventListener('resize', handleResize);
  await fetchPeriods();
  await loadCurrentBalance();
  await loadHistory();
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
});
</script>

<template>
  <section class="manual-balance">
    <header class="header">
      <div>
        <h1 class="page-title">Manual Balance</h1>
        <p class="subtitle text-muted">{{ periodName }}</p>
      </div>
      <div class="controls">
        <PeriodSelect v-model="selectedPeriodId" :periods="periods" label="Period" />
      </div>
    </header>

    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
    <div class="grid">
      <div class="card current">
        <div class="card__header">
          <h2 class="section-title">الرصيد اليدوي الحالي</h2>
        </div>
        <p v-if="loadingCurrent" class="muted">...جاري التحميل</p>
        <p v-else-if="errorCurrent" class="error">{{ errorCurrent }}</p>
        <div v-else-if="currentBalance" class="current__content">
          <StatCard title="الرصيد اليدوي" :value="currentBalance.manual_company_balance" variant="summary" />
          <p class="meta">
            آخر تحديث:
            {{ lastUpdatedAt || '—' }}
          </p>
          <p class="meta">
            ملاحظة:
            {{ currentBalance.note || '—' }}
          </p>
        </div>
        <p v-else class="muted">لا يوجد رصيد يدوي محفوظ لهذه الفترة.</p>
      </div>

      <div class="card form-card">
        <div class="card__header">
          <h2 class="section-title">إضافة / تحديث الرصيد اليدوي</h2>
        </div>
        <form class="form" :class="{ 'form--single': isMobile }" @submit.prevent="saveManualBalance">
          <label>
            الرصيد اليدوي للشركة
            <input
              v-model.number="form.manual_company_balance"
              type="number"
              step="0.01"
              min="0"
              required
            />
          </label>
          <label>
            ملاحظة
            <textarea v-model="form.note" rows="3" placeholder="اختياري"></textarea>
          </label>
          <p v-if="formMessage" :class="formMessage.includes('تعذر') ? 'error' : 'success'">
            {{ formMessage }}
          </p>
          <div class="actions" :class="{ 'actions--stack': isMobile }">
            <button class="primary-btn btn-responsive" type="submit" :disabled="saving">
              {{ saving ? 'جاري الحفظ...' : 'حفظ الرصيد اليدوي' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card history">
      <div class="card__header">
        <h2>سجل الأرصدة اليدوية</h2>
      </div>
      <div v-if="hasHistoryEndpoint">
        <p v-if="loadingHistory" class="muted">...جاري التحميل</p>
        <p v-else-if="errorHistory" class="error">{{ errorHistory }}</p>
        <p v-else-if="!history.length" class="muted">لا يوجد سجل للأرصدة اليدوية.</p>
        <ul v-else class="history-list">
          <li v-for="item in history" :key="item.id">
            <div class="history-row">
              <span class="value">{{ item.manual_company_balance }}</span>
              <span class="note">{{ item.note || '—' }}</span>
              <span class="date">{{ item.updated_at || item.created_at || '—' }}</span>
            </div>
          </li>
        </ul>
      </div>
      <div v-else class="muted">History is not available yet in this version.</div>
    </div>
  </section>
</template>

<style scoped>
.manual-balance {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.subtitle {
  margin: 4px 0 0;
  color: var(--color-text-muted);
}

.controls {
  display: flex;
  gap: 12px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
}

.card__header {
  margin-bottom: 8px;
}

.form {
  display: grid;
  gap: 12px;
}

.form--single {
  grid-template-columns: 1fr;
}

.actions {
  display: flex;
  justify-content: flex-end;
}

.actions--stack {
  flex-direction: column;
  gap: 8px;
}

.actions--stack button {
  width: 100%;
}

.current {
  border-left: 4px solid var(--color-primary);
  background: var(--color-primary-light);
}

.current__content {
  display: grid;
  gap: 8px;
}

.meta {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 14px;
}

.history-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}

.history-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 8px;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
}

.value {
  font-weight: 700;
  color: var(--color-text);
}

.note {
  color: var(--color-text);
}

.date {
  color: var(--color-text-muted);
  font-size: 13px;
}
</style>
