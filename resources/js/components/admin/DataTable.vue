<script setup lang="ts" generic="T extends Record<string, unknown>">
/**
 * Ant Design table behind the original slot API.
 *
 * Call sites keep passing `columns`/`rows` and filling `#cell-<key>` slots, so
 * the 14 pages using this didn't have to change. Sorting stays server-driven:
 * `sortOrder` is fed from props every render, which overrides AntD's internal
 * three-state cycle and keeps the header arrow honest about what the server
 * actually sorted by.
 */
import type { TableColumnType } from 'ant-design-vue';
import { Table } from 'ant-design-vue';
import { computed, useSlots } from 'vue';
import { useI18n } from 'vue-i18n';
import HelpHint from './HelpHint.vue';

const { t } = useI18n();
const slots = useSlots();

export interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
    /** Explanation behind a hint icon in the header, for columns whose heading
     *  does not say what the number actually counts. */
    help?: string;
}

const props = defineProps<{
    columns: Column[];
    rows: T[];
    sort?: string;
    dir?: string;
    rowKey?: string;
    /** Opt in to row activation: click or Enter/Space emits `row-click`. */
    clickableRows?: boolean;
}>();

const emit = defineEmits<{
    (e: 'sort', key: string): void;
    (e: 'row-click', row: T): void;
}>();

/** Sentinels for the two structural columns the slots imply. */
const SELECT_KEY = '__select__';
const ACTIONS_KEY = '__actions__';

function keyOf(row: T, index: number): string | number {
    const k = props.rowKey ?? 'id';
    const value = row[k];

    return typeof value === 'number' || typeof value === 'string' ? value : index;
}

function activate(row: T, event: Event): void {
    if (!props.clickableRows) return;

    // Checkboxes, edit/delete buttons and links inside the row keep their own
    // behaviour — activating the row must not fire on top of them.
    if ((event.target as HTMLElement | null)?.closest('a, button, input, select, textarea, label') !== null) return;

    emit('row-click', row);
}

function onKeydown(row: T, event: KeyboardEvent): void {
    if (event.key !== 'Enter' && event.key !== ' ') return;

    // Space scrolls the page by default; the row owns the key while focused.
    event.preventDefault();
    activate(row, event);
}

const tableColumns = computed<TableColumnType[]>(() => [
    ...(slots.select ? [{ key: SELECT_KEY, width: 48 }] : []),
    ...props.columns.map((col) => ({
        key: col.key,
        dataIndex: col.key,
        title: col.label,
        help: col.help,
        sorter: col.sortable ? true : undefined,
        sortOrder: col.sortable && props.sort === col.key ? ((props.dir === 'desc' ? 'descend' : 'ascend') as const) : null,
        customHeaderCell: () => ({ class: col.class }),
        customCell: () => ({ class: col.class }),
    })),
    // `text-end`, not AntD's `align: 'right'` — align emits `text-align: right`,
    // which stays pinned right under RTL instead of following the direction.
    ...(slots['row-actions']
        ? [
              {
                  key: ACTIONS_KEY,
                  title: t('admin.common.actions'),
                  customHeaderCell: () => ({ class: 'text-end' }),
                  customCell: () => ({ class: 'text-end' }),
              },
          ]
        : []),
]);

/** AntD owns the <tr>, so row activation is bound through customRow. */
function customRow(row: T): Record<string, unknown> {
    if (!props.clickableRows) return {};

    return {
        class: 'cursor-pointer focus:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 dark:focus:bg-gray-800/50',
        tabindex: 0,
        role: 'button',
        onClick: (event: Event) => activate(row, event),
        onKeydown: (event: KeyboardEvent) => onKeydown(row, event),
    };
}

/** AntD hands back the clicked column; the page owns the direction toggle. */
function onChange(_pagination: unknown, _filters: unknown, sorter: { columnKey?: string | number }): void {
    if (sorter?.columnKey) {
        emit('sort', String(sorter.columnKey));
    }
}
</script>

<template>
    <Table
        :columns="tableColumns"
        :data-source="rows"
        :row-key="keyOf"
        :pagination="false"
        class="admin-table"
        size="middle"
        :scroll="{ x: 'max-content' }"
        :custom-row="customRow"
        @change="onChange"
    >
        <!-- Rendering nothing lets AntD fall back to `column.title`, which is
             what every ordinary header wants; only the two special cases opt out. -->
        <template #headerCell="{ column }">
            <slot v-if="column.key === SELECT_KEY" name="select" />
            <span v-else-if="column.help" class="inline-flex items-center gap-1">
                {{ column.title }}
                <HelpHint :text="column.help" />
            </span>
        </template>

        <template #bodyCell="{ column, record }">
            <slot v-if="column.key === SELECT_KEY" name="row-select" :row="record as T" />
            <!-- -me-1 cancels the icon button's own inset so the glyph, not the
                 invisible hit area, lines up with the column heading. -->
            <div v-else-if="column.key === ACTIONS_KEY" class="-me-1 flex items-center justify-end gap-1">
                <slot name="row-actions" :row="record as T" />
            </div>
            <slot v-else :name="`cell-${column.key}`" :row="record as T">
                {{ record[column.key as string] ?? '—' }}
            </slot>
        </template>

        <template #emptyText>
            <slot name="empty">{{ t('admin.common.no_records') }}</slot>
        </template>
    </Table>
</template>
