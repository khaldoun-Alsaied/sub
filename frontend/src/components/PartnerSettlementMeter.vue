<script setup>
import { computed } from 'vue';

const props = defineProps({
  companyBalance: { type: [Number, String], default: 0 },
  companyExpenses: { type: [Number, String], default: 0 },
  withdrawalKhaled: { type: [Number, String], default: 0 },
  withdrawalOmar: { type: [Number, String], default: 0 },
  depositKhaled: { type: [Number, String], default: 0 },
  depositOmar: { type: [Number, String], default: 0 },
  expensePrivateKhaled: { type: [Number, String], default: 0 },
  expensePrivateOmar: { type: [Number, String], default: 0 },
  debtKhaledToOmar: { type: [Number, String], default: 0 },
  debtOmarToKhaled: { type: [Number, String], default: 0 },
  shareKhaled: { type: Number, default: 0.5 }, // نسبة خالد من الشراكة (0-1)
});

const summary = computed(() => {
  const bal = Number(props.companyBalance) || 0;
  const exp = Number(props.companyExpenses) || 0;
  const basePool = bal;

  const shareK = Math.max(0, Math.min(1, props.shareKhaled ?? 0.5));
  const shareO = 1 - shareK;

  const baseK = basePool * shareK;
  const baseO = basePool * shareO;

  const wK = Number(props.withdrawalKhaled) || 0;
  const wO = Number(props.withdrawalOmar) || 0;
  const dK = Number(props.depositKhaled) || 0;
  const dO = Number(props.depositOmar) || 0;
  const expPrivK = Number(props.expensePrivateKhaled) || 0;
  const expPrivO = Number(props.expensePrivateOmar) || 0;

  const debtKO = Number(props.debtKhaledToOmar) || 0; // خالد مدين لعمر
  const debtOK = Number(props.debtOmarToKhaled) || 0; // عمر مدين لخالد

  const netK =
    baseK - wK + dK + debtOK - debtKO + expPrivK / 2 - expPrivO / 2;
  const netO =
    baseO - wO + dO + debtKO - debtOK + expPrivO / 2 - expPrivK / 2;
  const expensePrivateTotal = expPrivK + expPrivO;

  const diff = netK - netO;
  const owner = diff > 0 ? 'لصالح خالد' : diff < 0 ? 'لصالح عمر' : 'متعادلة';
  const totalMag = Math.abs(netK) + Math.abs(netO);
  const percent = totalMag > 0 ? Math.min(100, (Math.abs(diff) / totalMag) * 100) : 0;
  const direction = diff > 0 ? 'right' : diff < 0 ? 'left' : 'none';

  return {
    basePool,
    baseK,
    baseO,
    netK,
    netO,
    expensePrivateTotal,
    expensePrivateKhaled: expPrivK,
    expensePrivateOmar: expPrivO,
    diff,
    owner,
    percent,
    direction,
  };
});
</script>

<template>
  <div class="meter-card">
    <div class="grid-row">
      <div class="stat-card stat-card--primary">
        <p class="label">رصيد الشركة (يدوي)</p>
        <p class="value">{{ summary.basePool.toLocaleString() }}</p>
      </div>
      <div class="stat-card stat-card--warning">
        <p class="label">مصاريف الشركة</p>
        <p class="value">{{ Number(companyExpenses || 0).toLocaleString() }}</p>
      </div>
      <div class="stat-card stat-card--info">
        <p class="label">مصاريف الشركة من الحساب الخاص</p>
        <p class="value">{{ summary.expensePrivateTotal.toLocaleString() }}</p>
        <p class="sub">
          خالد: {{ summary.expensePrivateKhaled.toLocaleString() }} | عمر:
          {{ summary.expensePrivateOmar.toLocaleString() }}
        </p>
      </div>
    </div>

    <div class="grid-row">
      <div class="stat-card stat-card--accent">
        <p class="label">حصة خالد من الحساب البنكي قبل الخصومات</p>
        <p class="value">{{ summary.baseK.toLocaleString() }}</p>
        <p class="sub">
        الحساب بعد الخصومات : {{ summary.baseK.toLocaleString() }} - سحب {{ Number(withdrawalKhaled || 0).toLocaleString() }} + إدخال
          {{ Number(depositKhaled || 0).toLocaleString() }} + نصف مصاريف خالد {{ (summary.expensePrivateKhaled / 2).toLocaleString() }} -
          نصف مصاريف عمر {{ (summary.expensePrivateOmar / 2).toLocaleString() }} = {{
            (
              summary.baseK -
              Number(withdrawalKhaled || 0) +
              Number(depositKhaled || 0) +
              summary.expensePrivateKhaled / 2 -
              summary.expensePrivateOmar / 2
            ).toLocaleString()
          }}
        </p>
      </div>
      <div class="stat-card stat-card--neutral">
        <p class="label">حصة عمر من الحساب البنكي قبل الخصومات</p>
        <p class="value">{{ summary.baseO.toLocaleString() }}</p>
        <p class="sub">
          الحساب بعد الخصومات : {{ summary.baseO.toLocaleString() }} - سحب {{ Number(withdrawalOmar || 0).toLocaleString() }} + إدخال
          {{ Number(depositOmar || 0).toLocaleString() }} + نصف مصاريف عمر {{ (summary.expensePrivateOmar / 2).toLocaleString() }} -
          نصف مصاريف خالد {{ (summary.expensePrivateKhaled / 2).toLocaleString() }} = {{
            (
              summary.baseO -
              Number(withdrawalOmar || 0) +
              Number(depositOmar || 0) +
              summary.expensePrivateOmar / 2 -
              summary.expensePrivateKhaled / 2
            ).toLocaleString()
          }}
        </p>
      </div>
    </div>

    <div class="grid-row">
      <div class="stat-card stat-card--danger">
        <p class="label">خالد مدين لعمر</p>
        <p class="value negative">{{ Number(debtKhaledToOmar || 0).toLocaleString() }}</p>
      </div>
      <div class="stat-card stat-card--success">
        <p class="label">عمر مدين لخالد</p>
        <p class="value positive">{{ Number(debtOmarToKhaled || 0).toLocaleString() }}</p>
      </div>
    </div>

    <div class="bar">
      <div class="bar__center-line"></div>
      <div
        class="bar__fill"
        :data-direction="summary.direction"
        :style="{ width: `${summary.percent}%` }"
      ></div>
      <div
        class="bar__thumb"
        :data-direction="summary.direction"
        :style="{ [summary.direction === 'left' ? 'left' : 'right']: summary.percent + '%' }"
        v-if="summary.direction !== 'none'"
      >
        {{ summary.owner }}
      </div>
      <div class="bar__thumb bar__thumb--neutral" v-else>متعادلة</div>
    </div>

    <div class="grid-row results">
      <div class="stat-card stat-card--accent">
        <p class="label">صافي خالد</p>
        <p class="value">{{ summary.netK.toLocaleString() }}</p>
        <p class="sub">
          الحساب: {{ summary.baseK.toLocaleString() }} - سحب {{ Number(withdrawalKhaled || 0).toLocaleString() }} + إدخال
          {{ Number(depositKhaled || 0).toLocaleString() }} + نصف مصروف خالد {{ (summary.expensePrivateKhaled / 2).toLocaleString() }} -
          نصف مصروف عمر {{ (summary.expensePrivateOmar / 2).toLocaleString() }} + ديون عمر لخالد {{ Number(debtOmarToKhaled || 0).toLocaleString() }} - ديون خالد لعمر
          {{ Number(debtKhaledToOmar || 0).toLocaleString() }}
        </p>
      </div>
      <div class="stat-card stat-card--neutral">
        <p class="label">صافي عمر</p>
        <p class="value">{{ summary.netO.toLocaleString() }}</p>
        <p class="sub">
          الحساب: {{ summary.baseO.toLocaleString() }} - سحب {{ Number(withdrawalOmar || 0).toLocaleString() }} + إدخال
          {{ Number(depositOmar || 0).toLocaleString() }} + نصف مصروف عمر {{ (summary.expensePrivateOmar / 2).toLocaleString() }} -
          نصف مصروف خالد {{ (summary.expensePrivateKhaled / 2).toLocaleString() }} + ديون خالد لعمر {{ Number(debtKhaledToOmar || 0).toLocaleString() }} - ديون عمر لخالد
          {{ Number(debtOmarToKhaled || 0).toLocaleString() }}
        </p>
      </div>
    </div>

    <p class="note note--plain">
      الفارق: {{ Math.abs(summary.diff).toLocaleString() }} ({{ summary.owner }})
    </p>
  </div>
</template>

<style scoped>
.meter-card {
  display: grid;
  gap: 12px;
}

.grid-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  align-items: stretch;
  gap: 12px;
}

.stat-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  background: #f8fafc;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}

.stat-card--primary {
  background: #eef2ff;
  border-color: #c7d2fe;
}

.stat-card--warning {
  background: #fff7ed;
  border-color: #fed7aa;
}

.stat-card--accent {
  background: #e0f2fe;
  border-color: #bae6fd;
}

.stat-card--info {
  background: #eef2ff;
  border-color: #c7d2fe;
}

.stat-card--neutral {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.stat-card--danger {
  background: #fef2f2;
  border-color: #fecdd3;
}

.stat-card--success {
  background: #ecfdf3;
  border-color: #bbf7d0;
}

.label {
  margin: 0;
  color: #475569;
  font-size: 13px;
}

.value {
  margin: 2px 0 0;
  font-weight: 800;
  font-size: 20px;
  color: #0f172a;
}

.value.negative {
  color: #b91c1c;
}

.value.positive {
  color: #0f766e;
}

.sub {
  margin: 2px 0 0;
  color: #64748b;
  font-size: 12px;
}

.muted {
  color: #94a3b8;
  font-size: 12px;
}

.bar {
  position: relative;
  height: 34px;
  background: linear-gradient(90deg, rgba(239, 68, 68, 0.08) 0%, rgba(37, 99, 235, 0.08) 100%);
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
}

.bar__center-line {
  position: absolute;
  left: 50%;
  top: 0;
  width: 1px;
  height: 100%;
  background: #cbd5e1;
}

.bar__fill {
  position: absolute;
  top: 0;
  height: 100%;
  background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
  opacity: 0.35;
  transition: width 0.3s ease;
}

.bar__fill[data-direction='right'] {
  left: 50%;
  transform-origin: left center;
}

.bar__fill[data-direction='left'] {
  right: 50%;
  transform-origin: right center;
  background: linear-gradient(90deg, #ef4444 0%, #f59e0b 100%);
}

.bar__thumb {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  white-space: nowrap;
  background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
}

.bar__thumb[data-direction='left'] {
  background: linear-gradient(90deg, #ef4444 0%, #f59e0b 100%);
  transform: translate(-50%, -50%);
}

.bar__thumb[data-direction='right'] {
  transform: translate(50%, -50%);
}

.bar__thumb--neutral {
  left: 50%;
  transform: translate(-50%, -50%);
  background: #64748b;
}

.results .value {
  font-size: 18px;
}

.note {
  margin: 0;
  color: #475569;
}

.note--plain {
  text-align: left;
  font-size: 14px;
  font-weight: 600;
}
</style>
