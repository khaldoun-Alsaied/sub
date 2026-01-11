<script setup>
const props = defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number, null], default: null },
  subtitle: { type: String, default: '' },
  variant: { type: String, default: 'default' }, // income | expense | summary | default
});

const formatValue = (val) => {
  if (val === null || val === undefined || val === '') return '—';
  return val;
};
</script>

<template>
  <div class="stat-card card" :data-variant="variant">
    <p class="stat-card__title text-muted">{{ title }}</p>
    <p class="stat-card__value">{{ formatValue(value) }}</p>
    <p v-if="subtitle" class="stat-card__subtitle text-muted">{{ subtitle }}</p>
  </div>
</template>

<style scoped>
.stat-card {
  display: grid;
  gap: 6px;
  border-left: 4px solid transparent;
}

.stat-card__title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}

.stat-card__value {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--color-text);
}

.stat-card__subtitle {
  margin: 0;
  font-size: 13px;
}

.stat-card[data-variant='income'] {
  border-left-color: var(--color-accent-green);
  background: var(--color-primary-light);
}

.stat-card[data-variant='expense'] {
  border-left-color: var(--color-accent-gold);
  background: var(--color-blush);
}

.stat-card[data-variant='summary'] {
  border-left-color: var(--color-primary);
  background: #fff;
}
</style>
