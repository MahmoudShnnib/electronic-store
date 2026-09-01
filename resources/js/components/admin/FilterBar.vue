<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Input } from 'ant-design-vue';
import { Search } from 'lucide-vue-next';
import { onBeforeUnmount, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminSelect from './AdminSelect.vue';
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
        <!-- Plain input, not InputSearch: the 400ms debounce already applies the
             query, so the attached submit button was a dead control. -->
        <Input
            v-model:value="state[searchKey]"
            :placeholder="searchPlaceholder ?? t('admin.common.search_default')"
            :aria-label="searchPlaceholder ?? t('admin.common.search_default')"
            type="search"
            name="q"
            autocomplete="off"
            spellcheck="false"
            allow-clear
            class="min-w-56 flex-1 md:max-w-sm"
            @press-enter="apply"
        >
            <template #prefix><Search class="h-4 w-4 text-black/25 dark:text-white/30" /></template>
        </Input>

        <AdminSelect
            v-for="select in selects ?? []"
            :key="select.key"
            v-model="state[select.key]"
            class="!w-auto min-w-40"
            :empty-label="select.label"
            :options="select.options"
            @update:model-value="apply"
        />

        <slot />
    </div>
</template>
