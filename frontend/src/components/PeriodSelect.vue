<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: null,
  },
  periods: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: 'Period',
  },
});

const emit = defineEmits(['update:modelValue']);

const internalValue = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const handleChange = (event) => {
  const value = event.target.value;
  const parsed = value === '' ? null : Number(value);
  internalValue.value = Number.isNaN(parsed) ? value : parsed;
};
</script>

<template>
  <label class="period-select">
    <span class="period-select__label">{{ label }}</span>
    <select
      class="period-select__control"
      :value="internalValue"
      :disabled="!periods.length"
      @change="handleChange"
    >
      <option v-for="p in periods" :key="p.id" :value="p.id">
        {{ p.name }}
      </option>
    </select>
  </label>
</template>

<style scoped>
.period-select {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 14px;
  color: #475569;
  min-width: 180px;
}

.period-select__label {
  font-weight: 600;
}

.period-select__control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
}
</style>
