<script setup>
import { computed } from 'vue';

const props = defineProps({
  khaledToOmar: { type: [Number, String], default: 0 },
  omarToKhaled: { type: [Number, String], default: 0 },
});

const parsed = computed(() => {
  const k = Number(props.khaledToOmar) || 0;
  const o = Number(props.omarToKhaled) || 0;
  const total = k + o;
  const diff = k - o;
  const percent = total > 0 ? Math.min(100, Math.abs(diff) / total * 100) : 0;
  const owner = diff > 0 ? 'لصالح عمر' : diff < 0 ? 'لصالح خالد' : 'متعادلة';
  const direction = diff > 0 ? 'right' : diff < 0 ? 'left' : 'none';
  return { k, o, total, diff, percent, owner, direction };
});
</script>

<template>
  <div class="balance-card">
    <div class="balance-header">
      <div>
        <p class="label">خالد مدين لعمر</p>
        <p class="value">{{ parsed.k.toLocaleString() }}</p>
      </div>
      <div class="divider"></div>
      <div>
        <p class="label">عمر مدين لخالد</p>
        <p class="value">{{ parsed.o.toLocaleString() }}</p>
      </div>
    </div>

    <div class="delta">
      <span class="delta__label">الفارق</span>
      <span class="delta__value">{{ Math.abs(parsed.diff).toLocaleString() }}</span>
      <span class="delta__owner">({{ parsed.owner }})</span>
    </div>

    <div class="bar">
      <div class="bar__center-line"></div>
      <div
        class="bar__fill"
        :data-direction="parsed.direction"
        :style="{
          width: `${parsed.percent}%`,
        }"
      ></div>
      <div
        class="bar__thumb"
        :data-direction="parsed.direction"
        :style="{ [parsed.direction === 'left' ? 'left' : 'right']: parsed.percent + '%' }"
        v-if="parsed.direction !== 'none'"
      >
        {{ parsed.owner }}
      </div>
      <div class="bar__thumb bar__thumb--neutral" v-else>متعادلة</div>
    </div>

  </div>
</template>

<style scoped>
.balance-card {
  display: grid;
  gap: 10px;
}

.balance-header {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 12px;
}

.divider {
  width: 1px;
  height: 32px;
  background: #e2e8f0;
}

.label {
  margin: 0;
  color: #475569;
  font-size: 13px;
}

.value {
  margin: 2px 0 0;
  font-weight: 800;
  font-size: 18px;
  color: #0f172a;
}

.delta {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(124, 58, 237, 0.12));
  color: #0f172a;
  font-weight: 800;
}

.delta__label {
  font-size: 13px;
  color: #334155;
}

.delta__value {
  font-size: 20px;
}

.delta__owner {
  font-size: 13px;
  color: #475569;
}

.bar {
  position: relative;
  height: 30px;
  background: linear-gradient(90deg, rgba(239, 68, 68, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
  border: 1px solid #e2e8f0;
  border-radius: 12px;
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
  background: linear-gradient(90deg, #ef4444 0%, #f59e0b 100%);
  opacity: 0.4;
  transition: width 0.3s ease;
}

.bar__fill[data-direction='right'] {
  left: 50%;
  transform-origin: left center;
  background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
}

.bar__fill[data-direction='left'] {
  right: 50%;
  transform-origin: right center;
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

.note {
  margin: 0;
  color: #475569;
  font-size: 13px;
}
</style>
