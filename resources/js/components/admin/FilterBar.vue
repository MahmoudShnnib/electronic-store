<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onBeforeUnmount, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Option } from './types';

const { t } = useI18n();

export interface SelectFilter {
    key: string;
    label: string;
    options: Option[];
}

const props = defineProps<{
    url: string;
    filters: Record<string, unknown>;
    selects?: SelectFilter[];
    searchPlaceholder?: string;
    searchKey?: string;
}>();

const state = reactive<Record<string, string>>({});

const searchKey = props.searchKey ?? 'q';

state[searchKey] = String(props.filters[searchKey] ?? '');

for (const select of props.selects ?? []) {
    state[select.key] = String(props.filters[select.key] ?? '');
}

let timer: ReturnType<typeof setTimeout> | null = null;

function apply(): void {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(state)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    router.get(props.url, query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(
    () => state[searchKey],
    () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 400);
    },
);

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative min-w-56 flex-1 md:max-w-sm">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
                v-model="state[searchKey]"
                type="search"
                :placeholder="searchPlaceholder ?? t('admin.common.search_default')"
                class="w-full rounded-md border border-gray-300 bg-white py-2 pe-3 ps-9 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            />
        </div>

        <select
            v-for="select in selects ?? []"
            :key="select.key"
            v-model="state[select.key]"
            class="rounded-md border border-gray-300 bg-white py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            @change="apply"
        >
            <option value="">{{ select.label }}</option>
            <option v-for="option in select.options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <slot />
    </div>
</template>
