<script setup lang="ts">
import { Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

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
    <Modal
        :open="open"
        :title="title ?? t('admin.common.are_you_sure')"
        :ok-text="confirmLabel ?? t('admin.common.confirm')"
        :cancel-text="t('admin.common.cancel')"
        :ok-button-props="{ danger, loading: processing }"
        centered
        @ok="emit('confirm')"
        @cancel="emit('close')"
    >
        <p>{{ message }}</p>
    </Modal>
</template>
