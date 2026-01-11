<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import httpClient from '@/api/httpClient';
import PeriodSelect from '@/components/PeriodSelect.vue';
import StatCard from '@/components/StatCard.vue';
import DashboardSummaryChart from '@/components/DashboardSummaryChart.vue';
import PartnerDebtChart from '@/components/PartnerDebtChart.vue';
import PartnerDebtBalanceMeter from '@/components/PartnerDebtBalanceMeter.vue';
import PartnerSettlementMeter from '@/components/PartnerSettlementMeter.vue';
import '@/styles/dashboard.css';

const router = useRouter();

const periods = ref([]);
const selectedPeriodId = ref(null);
const summary = ref(null);
const loading = ref(false);
const error = ref(null);

const periodName = computed(
  () => periods.value.find((p) => p.id === selectedPeriodId.value)?.name || ''
);

const incomes = computed(() => {
  if (!summary.value) return [];
  return [
    {
      label: 'المبيعات',
      value:
        (summary.value.manual_company_balance ?? 0) +
        (summary.value.total_expenses || 0) -
        (summary.value.total_income || 0),
      variant: 'income',
    },
    { label: 'دخل Khaled', value: summary.value.income_person1, variant: 'income' },
    { label: 'دخل Omar', value: summary.value.income_person2, variant: 'income' },
  ];
});

const expenses = computed(() => {
  if (!summary.value) return [];
  return [
    { label: 'مصاريف الشركة', value: summary.value.total_expenses, variant: 'expense' },
    { label: 'سحب Khaled', value: summary.value.withdrawal_person1, variant: 'expense' },
    { label: 'سحب Omar', value: summary.value.withdrawal_person2, variant: 'expense' },
  ];
});

const summaryCards = computed(() => {
  if (!summary.value) return [];
  return [
    {
      label: 'صافي الربح',
      value: summary.value.net_company_profit,
      subtitle: '(net_company_profit)',
      variant: 'summary',
    },
    { label: 'الرصيد الفعلي', value: summary.value.manual_company_balance, variant: 'summary' },
    { label: 'الرصيد النظري', value: summary.value.theoretical_balance, variant: 'summary' },
    { label: 'الفرق', value: summary.value.difference, variant: 'summary' },
  ];
});

const partnerDebtCards = computed(() => {
  if (!summary.value) return [];
  const khaledToOmar = Number(summary.value.debt_khaled_to_omar || 0);
  const omarToKhaled = Number(summary.value.debt_omar_to_khaled || 0);
  const diff = khaledToOmar - omarToKhaled;
  const owner = diff > 0 ? 'لصالح عمر' : diff < 0 ? 'لصالح خالد' : 'متعادلة';
  return [
    { label: 'عمر مدين لخالد', value: omarToKhaled, variant: 'summary' },
    { label: 'خالد مدين لعمر', value: khaledToOmar, variant: 'summary' },
    { label: 'الفارق بين الديون', value: Math.abs(diff), subtitle: owner, variant: 'summary' },
  ];
});

const fetchPeriods = async () => {
  try {
    const { data } = await httpClient.get('/periods');
    periods.value = data || [];

    if (periods.value.length > 0) {
      selectedPeriodId.value = periods.value[0].id;
      await loadSummary();
    }
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load periods';
    if (err.response?.status === 401) {
      router.push('/login');
    }
  }
};

const loadSummary = async () => {
  if (!selectedPeriodId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const { data } = await httpClient.get('/dashboard/summary', {
      params: { period_id: selectedPeriodId.value },
    });
    summary.value = data;
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load summary';
    if (err.response?.status === 401) {
      router.push('/login');
    }
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchPeriods();
});
</script>

<template>
  <section class="dashboard">
    <div class="dashboard__topline">
      <div class="dashboard__header">
        <h1 class="page-title">Dashboard</h1>
        <p class="dashboard__meta">
          {{ periodName ? `الفترة الحالية: ${periodName}` : 'لا توجد فترة محددة' }}
        </p>
      </div>
      <PeriodSelect
        v-model="selectedPeriodId"
        :periods="periods"
        label="Period"
        @update:modelValue="loadSummary"
      />
    </div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="loading" class="status">جاري التحميل...</p>

    <div v-if="summary">
      <div class="hero-grid">
        <div class="chart-card card card--accent">
          <div class="chart-card__header">
            <h2 class="section-title">مؤشر تسوية الشركاء</h2>
            <p class="text-muted">صافي ما يستحقه كل شريك بعد المصاريف والسحب والإدخالات والديون</p>
          </div>
          <PartnerSettlementMeter
            :company-balance="summary.manual_company_balance ?? summary.theoretical_balance ?? 0"
            :company-expenses="summary.total_expenses || 0"
            :withdrawal-khaled="summary.withdrawal_person1 || 0"
            :withdrawal-omar="summary.withdrawal_person2 || 0"
            :deposit-khaled="summary.income_person1 || 0"
            :deposit-omar="summary.income_person2 || 0"
            :expense-private-khaled="summary.expense_private_khaled || 0"
            :expense-private-omar="summary.expense_private_omar || 0"
            :debt-khaled-to-omar="summary.debt_khaled_to_omar || 0"
            :debt-omar-to-khaled="summary.debt_omar_to_khaled || 0"
            :share-khaled="0.5"
          />
        </div>
      </div>

      <div class="stat-section">
        <h3 class="section-title">الدخل</h3>
        <div class="stat-grid">
          <StatCard
            v-for="card in incomes"
            :key="card.label"
            :title="card.label"
            :value="card.value"
            :subtitle="card.subtitle"
            :variant="card.variant"
          />
        </div>
      </div>

      <div class="stat-section">
        <h3 class="section-title">المصروفات والسحب</h3>
        <div class="stat-grid">
          <StatCard
            v-for="card in expenses"
            :key="card.label"
            :title="card.label"
            :value="card.value"
            :subtitle="card.subtitle"
            :variant="card.variant"
          />
        </div>
      </div>

      <div class="stat-section">
        <h3 class="section-title">ملخص الرصيد</h3>
        <div class="stat-grid">
          <StatCard
            v-for="card in summaryCards"
            :key="card.label"
            :title="card.label"
            :value="card.value"
            :subtitle="card.subtitle"
            :variant="card.variant"
          />
        </div>
      </div>

      <div class="stat-section">
        <h3 class="section-title">ديون بين الشريكين</h3>
        <div class="stat-grid">
          <StatCard
            v-for="card in partnerDebtCards"
            :key="card.label"
            :title="card.label"
            :value="card.value"
            :subtitle="card.subtitle"
            :variant="card.variant"
          />
        </div>
      </div>

      <div class="summary-card card">
        <h3 class="section-title">ملخص الفترة الحالية</h3>
        <p class="summary-card__text text-muted">
          الدخل الكلي: {{ summary.total_income || 0 }} | المصاريف:
          {{ summary.total_expenses || 0 }} | السحوبات:
          {{ (summary.withdrawal_person1 || 0) + (summary.withdrawal_person2 || 0) }} | الصافي:
          {{ summary.net_company_profit || 0 }}
        </p>
      </div>

      <div class="chart-card card">
        <div class="chart-card__header">
          <h2 class="section-title">نظرة عامة</h2>
          <p class="text-muted">الدخل مقابل المصروفات والسحب</p>
        </div>
        <DashboardSummaryChart
          :income="summary.total_income"
          :expenses="summary.total_expenses"
          :withdrawals="(summary.withdrawal_person1 || 0) + (summary.withdrawal_person2 || 0)"
        />
      </div>

      <div class="chart-grid">
        <div class="chart-card card">
          <div class="chart-card__header">
            <h2 class="section-title">مقارنة ديون الشركاء</h2>
            <p class="text-muted">مجموع ما يدين به كل شريك للآخر</p>
          </div>
          <PartnerDebtChart
            :khaled-to-omar="summary.debt_khaled_to_omar || 0"
            :omar-to-khaled="summary.debt_omar_to_khaled || 0"
          />
        </div>

        <div class="chart-card card">
          <div class="chart-card__header">
            <h2 class="section-title">مؤشر التوازن بين الديون</h2>
            <p class="text-muted">شريط واحد يوضح أي كفة أرجح حالياً</p>
          </div>
          <PartnerDebtBalanceMeter
            :khaled-to-omar="summary.debt_khaled_to_omar || 0"
            :omar-to-khaled="summary.debt_omar_to_khaled || 0"
          />
        </div>
      </div>
    </div>
    <p v-else-if="!loading && !error" class="muted">No data for this period yet.</p>
  </section>
</template>
