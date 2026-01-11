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
  khaledToOmar: { type: [Number, String], default: 0 },
  omarToKhaled: { type: [Number, String], default: 0 },
});

const canvasRef = ref(null);
let chartInstance = null;

const renderChart = () => {
  const ctx = canvasRef.value?.getContext('2d');
  if (!ctx) return;

  const khaledOwesGradient = ctx.createLinearGradient(0, 0, 400, 0);
  khaledOwesGradient.addColorStop(0, '#2563eb');
  khaledOwesGradient.addColorStop(1, '#7c3aed');

  const omarOwesGradient = ctx.createLinearGradient(400, 0, 0, 0);
  omarOwesGradient.addColorStop(0, '#f59e0b');
  omarOwesGradient.addColorStop(1, '#ef4444');

  const khaledToOmarVal = Number(props.khaledToOmar) || 0;
  const omarToKhaledVal = Number(props.omarToKhaled) || 0;

  const data = {
    labels: ['الفارق بين الديون'],
    datasets: [
      {
        label: 'خالد مدين لعمر',
        data: [khaledToOmarVal],
        backgroundColor: khaledOwesGradient,
        borderColor: '#1d4ed8',
        borderWidth: 1.5,
        borderRadius: 10,
        barThickness: 26,
        stack: 'debt',
      },
      {
        label: 'عمر مدين لخالد',
        data: [-omarToKhaledVal],
        backgroundColor: omarOwesGradient,
        borderColor: '#d97706',
        borderWidth: 1.5,
        borderRadius: 10,
        barThickness: 26,
        stack: 'debt',
      },
    ],
  };

  const valueLabelPlugin = {
    id: 'valueLabel',
    afterDatasetsDraw(chart) {
      const { ctx, data } = chart;
      ctx.save();
      ctx.font = '600 12px sans-serif';
      ctx.fillStyle = '#0f172a';
      const meta = chart.getDatasetMeta(0);
      chart.data.datasets.forEach((dataset, datasetIndex) => {
        const bars = chart.getDatasetMeta(datasetIndex).data;
        bars.forEach((bar, idx) => {
          const val = Math.abs(dataset.data[idx] || 0);
          const { x, y } = bar.tooltipPosition();
          ctx.fillText(val.toLocaleString(), x + (dataset.data[idx] < 0 ? -30 : 12), y + 4);
        });
      });
      ctx.restore();
    },
  };

  const span = Math.max(khaledToOmarVal, omarToKhaledVal, 1);
  const padding = span * 0.15;

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
      legend: { position: 'top' },
      tooltip: {
        callbacks: {
          label: (ctx) => `${ctx.dataset.label}: ${Math.abs(ctx.parsed.x)}`,
        },
      },
    },
    scales: {
      x: {
        stacked: true,
        ticks: { color: '#374151' },
        grid: { color: '#e5e7eb' },
        title: { display: true, text: 'المبلغ', color: '#4b5563' },
        min: -(span + padding),
        max: span + padding,
      },
      y: {
        stacked: true,
        ticks: { color: '#374151' },
        grid: { color: '#e5e7eb' },
        title: { display: false },
      },
    },
  };

  if (chartInstance) {
    chartInstance.data = data;
    chartInstance.options = options;
    chartInstance.update();
    return;
  }

  chartInstance = new Chart(ctx, { type: 'bar', data, options, plugins: [valueLabelPlugin] });
};

watch(
  () => [props.khaledToOmar, props.omarToKhaled],
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
