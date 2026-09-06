<script setup lang="ts">
import { Modal } from 'ant-design-vue';

defineProps<{
    open: boolean;
    title: string;
    wide?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();
</script>

<template>
    <!-- Footer is null because every call site renders its own submit row inside
         the default slot; AntD's OK/Cancel pair would duplicate it. -->
    <Modal
        :open="open"
        :title="title"
        :width="wide ? 768 : 512"
        :footer="null"
        :body-style="{ maxHeight: 'calc(100vh - 10rem)', overflowY: 'auto' }"
        destroy-on-close
        centered
        @cancel="emit('close')"
    >
        <slot />
    </Modal>
</template>
