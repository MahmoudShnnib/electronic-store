<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import FormModal from './FormModal.vue';
import { btnDanger, btnPrimary, btnSecondary } from './ui';

const { t } = useI18n();

defineProps<{
    open: boolean;
    title?: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
    processing?: boolean;
}>();

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'close'): void;
}>();
</script>

<template>
    <FormModal :open="open" :title="title ?? t('admin.common.are_you_sure')" @close="emit('close')">
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ message }}</p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" :class="btnSecondary" @click="emit('close')">
                {{ t('admin.common.cancel') }}
            </button>
            <button type="button" :disabled="processing" :class="danger ? btnDanger : btnPrimary" @click="emit('confirm')">
                {{ confirmLabel ?? t('admin.common.confirm') }}
            </button>
        </div>
    </FormModal>
</template>
