<script setup lang="ts" generic="T extends Record<string, unknown>">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
}

const props = defineProps<{
    columns: Column[];
    rows: T[];
    sort?: string;
    dir?: string;
    rowKey?: string;
}>();

const emit = defineEmits<{
    (e: 'sort', key: string): void;
}>();

function keyOf(row: T, index: number): string | number {
    const k = props.rowKey ?? 'id';
    const value = row[k];

    return typeof value === 'number' || typeof value === 'string' ? value : index;
}
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950/50">
                <tr>
                    <th v-if="$slots.select" class="w-10 px-3 py-2.5" scope="col">
                        <slot name="select" />
                    </th>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        scope="col"
                        class="px-3 py-2.5 text-start text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        :class="col.class"
                    >
                        <button
                            v-if="col.sortable"
                            type="button"
                            class="inline-flex items-center gap-1 hover:text-gray-800 dark:hover:text-gray-200"
                            @click="emit('sort', col.key)"
                        >
                            {{ col.label }}
                            <ArrowUp v-if="sort === col.key && dir !== 'desc'" class="h-3 w-3" />
                            <ArrowDown v-else-if="sort === col.key" class="h-3 w-3" />
                            <ArrowUpDown v-else class="h-3 w-3 opacity-40" />
                        </button>
                        <span v-else>{{ col.label }}</span>
                    </th>
                    <th
                        v-if="$slots.actions || $slots['row-actions']"
                        scope="col"
                        class="px-3 py-2.5 text-end text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                    >
                        {{ t('admin.common.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="(row, index) in rows" :key="keyOf(row, index)" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td v-if="$slots.select" class="px-3 py-2.5">
                        <slot name="row-select" :row="row" />
                    </td>
                    <td v-for="col in columns" :key="col.key" class="px-3 py-2.5 text-gray-700 dark:text-gray-300" :class="col.class">
                        <slot :name="`cell-${col.key}`" :row="row">
                            {{ row[col.key] ?? '—' }}
                        </slot>
                    </td>
                    <td v-if="$slots['row-actions']" class="px-3 py-2.5 text-end">
                        <div class="flex items-center justify-end gap-1.5">
                            <slot name="row-actions" :row="row" />
                        </div>
                    </td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length + 2" class="px-3 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        <slot name="empty">{{ t('admin.common.no_records') }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
