<script setup lang="ts">
import { Button, InputNumber, Space } from 'ant-design-vue';
import { Minus, Plus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        modelValue: number;
        min?: number;
        max?: number;
    }>(),
    { min: 1, max: 10 },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: number): void }>();

const { t } = useI18n();

function set(value: number | string | null) {
    const next = Number(value);

    emit('update:modelValue', Math.min(props.max, Math.max(props.min, Number.isFinite(next) ? next : props.min)));
}
</script>

<template>
    <!-- `Space.Compact` is AntD's own way to weld controls into one field; the
         stepper arrows are turned off because the flanking buttons are the
         larger, touch-friendly version of the same thing. -->
    <Space.Compact class="store-qty">
        <Button :disabled="modelValue <= min" :aria-label="t('product.decrease_quantity')" @click="set(modelValue - 1)">
            <template #icon><Minus class="h-4 w-4" /></template>
        </Button>
        <InputNumber :value="modelValue" :min="min" :max="max" :controls="false" class="store-qty-field" @change="set" />
        <Button :disabled="modelValue >= max" :aria-label="t('product.increase_quantity')" @click="set(modelValue + 1)">
            <template #icon><Plus class="h-4 w-4" /></template>
        </Button>
    </Space.Compact>
</template>
