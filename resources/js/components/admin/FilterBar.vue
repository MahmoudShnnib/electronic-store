<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onBeforeUnmount, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminSelect from './AdminSelect.vue';
import type { Option } from './types';
import { searchInputClass } from './ui';

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
                :class="searchInputClass"
            />
        </div>

        <!-- `!w-auto`: the trigger carries w-full so it fills a form field, and
             that would stretch each filter across the whole bar and push the
             next one onto its own row. The min-width keeps longer labels from
             collapsing to a sliver. -->
        <AdminSelect
            v-for="select in selects ?? []"
            :key="select.key"
            v-model="state[select.key]"
            class="!w-auto min-w-40 max-w-56"
            :empty-label="select.label"
            :options="select.options"
            @update:model-value="apply"
        />

        <slot />
    </div>
</template>
