<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8">
            <div class="fixed inset-0 bg-black/50" aria-hidden="true" @click="emit('close')" />
            <div
                role="dialog"
                aria-modal="true"
                class="relative z-10 my-auto w-full rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900"
                :class="wide ? 'max-w-3xl' : 'max-w-lg'"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ title }}</h2>
                    <button
                        type="button"
                        class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                        :aria-label="t('admin.common.close')"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <slot />
            </div>
        </div>
    </Teleport>
</template>
