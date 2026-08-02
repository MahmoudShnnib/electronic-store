<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error';
    message: string;
}

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string | null; error?: string | null });

const toasts = ref<Toast[]>([]);
let nextId = 0;

function push(type: Toast['type'], message: string) {
    // Same message again just resets its timer instead of stacking a twin —
    // tapping "add to cart" three times shouldn't cover the screen in pills.
    const existing = toasts.value.find((toast) => toast.type === type && toast.message === message);

    if (existing) {
        clearTimeout(timers.get(existing.id));
        timers.set(
            existing.id,
            setTimeout(() => dismiss(existing.id), 3000),
        );

        return;
    }

    const id = nextId++;
    toasts.value.push({ id, type, message });
    timers.set(
        id,
        setTimeout(() => dismiss(id), 3000),
    );
}

const timers = new Map<number, ReturnType<typeof setTimeout>>();

function dismiss(id: number) {
    clearTimeout(timers.get(id));
    timers.delete(id);
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

watch(
    flash,
    (value) => {
        if (value?.success) push('success', value.success);
        if (value?.error) push('error', value.error);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <!-- Centered above the mobile tab bar; centering keeps LTR and RTL identical. -->
        <div class="pointer-events-none fixed inset-x-4 bottom-24 z-[100] flex flex-col items-center gap-2 lg:bottom-6">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <button
                    v-for="toast in toasts"
                    :key="toast.id"
                    type="button"
                    class="pointer-events-auto flex max-w-full items-center gap-2.5 rounded-full bg-[#111111] px-5 py-3 text-white shadow-2xl shadow-black/30"
                    role="status"
                    @click="dismiss(toast.id)"
                >
                    <CheckCircle2 v-if="toast.type === 'success'" class="h-5 w-5 shrink-0 text-green-400" />
                    <XCircle v-else class="h-5 w-5 shrink-0 text-red-400" />
                    <span class="truncate text-sm font-medium">{{ toast.message }}</span>
                </button>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
