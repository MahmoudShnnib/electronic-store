<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import FormModal from './FormModal.vue';

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
            <button
                type="button"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                @click="emit('close')"
            >
                {{ t('admin.common.cancel') }}
            </button>
            <button
                type="button"
                :disabled="processing"
                class="rounded-md px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                @click="emit('confirm')"
            >
                {{ confirmLabel ?? t('admin.common.confirm') }}
            </button>
        </div>
    </FormModal>
</template>
