<script setup lang="ts">
/**
 * Ant Design date picker behind the original string contract.
 *
 * Emits exactly what the forms already send: "YYYY-MM-DD", or
 * "YYYY-MM-DDTHH:mm" when `withTime` is set, with '' meaning unset. AntD's
 * `value-format` does the dayjs⇄string conversion, so no parsing here.
 *
 * Calendar localization and direction come from the ConfigProvider in
 * AdminLayout, which is what the hand-rolled version had to do itself.
 */
import { DatePicker } from 'ant-design-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        withTime?: boolean;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    { withTime: false, disabled: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const { t } = useI18n();

const format = computed(() => (props.withTime ? 'YYYY-MM-DDTHH:mm' : 'YYYY-MM-DD'));

const inner = computed({
    get: () => (props.modelValue === '' ? null : props.modelValue),
    set: (value: string | null) => emit('update:modelValue', value ?? ''),
});
</script>

<template>
    <DatePicker
        v-model:value="inner"
        :value-format="format"
        :show-time="withTime ? { format: 'HH:mm' } : false"
        :disabled="disabled"
        :placeholder="placeholder ?? t('admin.common.pick_date')"
        class="w-full"
    />
</template>
