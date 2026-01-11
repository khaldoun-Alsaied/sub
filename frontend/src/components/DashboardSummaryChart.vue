<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import {
  Chart,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
  income: { type: [Number, Array], default: 0 },
  expenses: { type: [Number, Array], default: 0 },
  withdrawals: { type: [Number, Array], default: 0 },
  labels: { type: Array, default: () => ['Income', 'Expenses', 'Withdrawals'] },
});

const canvasRef = ref(null);
let chartInstance = null;

const buildDataArray = (value) => (Array.isArray(value) ? value : [Number(value || 0)]);
const datasetLabels = ['Income', 'Expenses', 'Withdrawals'];

const renderChart = () => {
  const ctx = canvasRef.value?.getContext('2d');
  if (!ctx) return;

  const incomes = buildDataArray(props.income);
  const expenses = buildDataArray(props.expenses);
  const withdrawals = buildDataArray(props.withdrawals);

  const labels =
    props.labels && props.labels.length === incomes.length
      ? props.labels
      : datasetLabels.slice(0, incomes.length);

  const data = {
    labels,
    datasets: [
      {
        label: 'Income',
        data: incomes,
        backgroundColor: '#6BAA75',
        borderColor: '#6BAA75',
        borderWidth: 1,
      },
      {
        label: 'Expenses',
        data: expenses,
        backgroundColor: '#C6534F',
        borderColor: '#C6534F',
        borderWidth: 1,
      },
      {
        label: 'Withdrawals',
        data: withdrawals,
        backgroundColor: '#D9A441',
        borderColor: '#D9A441',
        borderWidth: 1,
      },
    ],
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          color: '#333333',
        },
      },
      tooltip: {
        callbacks: {
          label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}`,
        },
      },
    },
    scales: {
      x: {
        ticks: { color: '#777777' },
        grid: { display: false },
      },
      y: {
        ticks: { color: '#777777' },
        grid: { color: '#f0f0f0' },
      },
    },
  };

  if (chartInstance) {
    chartInstance.data = data;
    chartInstance.options = options;
    chartInstance.update();
    return;
  }

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data,
    options,
  });
};

watch(
  () => [props.income, props.expenses, props.withdrawals, props.labels],
  () => renderChart(),
  { deep: true }
);

onMounted(() => {
  renderChart();
});

onBeforeUnmount(() => {
  if (chartInstance) {
    chartInstance.destroy();
    chartInstance = null;
  }
});
</script>

<template>
  <div class="chart-wrapper">
    <canvas ref="canvasRef"></canvas>
  </div>
</template>

<style scoped>
.chart-wrapper {
  position: relative;
  width: 100%;
  min-height: 260px;
}

@media (max-width: 640px) {
  .chart-wrapper {
    min-height: 220px;
  }
}
</style>
