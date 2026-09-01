<script setup lang="ts">
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { TableColumnType } from 'ant-design-vue';
import { Table, Typography } from 'ant-design-vue';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t, te } = useI18n();

const { Text } = Typography;

/**
 * Activity rows store machine keys (`po_received`) and raw DB columns
 * (`price_cents`). Both were rendered verbatim, so the page stayed English on
 * the Arabic site and read like debug output. Unknown keys fall back to a
 * humanized version of the key rather than showing a missing-translation path.
 * Values (money, enum labels, booleans) arrive already localized from the
 * controller.
 */
function eventLabel(event: string | null, description: string): string {
    if (!event) return description;

    return te(`admin.audit.events.${event}`) ? t(`admin.audit.events.${event}`) : humanize(event);
}

function fieldLabel(field: string): string {
    return te(`admin.audit.fields.${field}`) ? t(`admin.audit.fields.${field}`) : humanize(field);
}

function subjectLabel(row: LogRow): string {
    if (!row.subject_type) return '—';

    const type = te(`admin.audit.subjects.${row.subject_type}`) ? t(`admin.audit.subjects.${row.subject_type}`) : row.subject_type;

    return `${type} · ${row.subject_label ?? `#${row.subject_id}`}`;
}

function humanize(key: string): string {
    const words = key
        .replace(/_cents$/, '')
        .replace(/_id$/, '')
        .replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

interface LogRow {
    id: number;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    causer: string;
    changes: { field: string; old: unknown; new: unknown }[];
    details: { field: string; value: unknown }[];
    ip: string | null;
    user_agent: string | null;
    created_at: string;
}

function display(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

defineProps<{
    logs: Paginator<LogRow>;
    subjectTypes: { value: string; label: string }[];
    filters: Record<string, unknown>;
}>();

const expanded = ref<Set<number>>(new Set());

const expandedKeys = computed(() => [...expanded.value]);

function toggle(id: number): void {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
    expanded.value = new Set(expanded.value);
}

const columns: TableColumnType[] = [
    { key: 'description', dataIndex: 'description', title: t('admin.audit.event') },
    { key: 'subject', dataIndex: 'subject_type', title: t('admin.audit.subject') },
    { key: 'causer', dataIndex: 'causer', title: t('admin.common.user') },
    { key: 'created_at', dataIndex: 'created_at', title: t('admin.common.date') },
];

const changeColumns: TableColumnType[] = [
    { key: 'field', dataIndex: 'field', title: t('admin.audit.field') },
    { key: 'old', dataIndex: 'old', title: t('admin.audit.before') },
    { key: 'new', dataIndex: 'new', title: t('admin.audit.after') },
];
</script>

<template>
    <Head :title="t('admin.audit.title')" />

    <PageHeader :title="t('admin.audit.title')" :subtitle="t('admin.audit.subtitle')" />

    <FilterBar
        :url="route('admin.audit-logs.index')"
        :filters="filters"
        :search-placeholder="t('admin.audit.search')"
        :selects="[{ key: 'subject_type', label: t('admin.common.all_subjects'), options: subjectTypes }]"
    />

    <!-- The whole row is the expand affordance, as before: `customRow` owns the
         toggle and the custom expand icon deliberately never calls `onExpand`,
         so a click on the chevron bubbles up instead of toggling twice. -->
    <Table
        :columns="columns"
        :data-source="logs.data"
        row-key="id"
        :pagination="false"
        size="middle"
        :scroll="{ x: 'max-content' }"
        :expanded-row-keys="expandedKeys"
        :custom-row="(record: LogRow) => ({ onClick: () => toggle(record.id), style: { cursor: 'pointer' } })"
    >
        <template #expandIcon="{ expanded: isExpanded }">
            <ChevronDown v-if="isExpanded" class="inline-block h-4 w-4 align-middle text-gray-400" />
            <ChevronRight v-else class="inline-block h-4 w-4 align-middle text-gray-400" />
        </template>

        <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'description'">
                {{ eventLabel(record.event, record.description) }}
            </template>
            <template v-else-if="column.key === 'subject'">
                <Text type="secondary">{{ subjectLabel(record as LogRow) }}</Text>
            </template>
            <template v-else-if="column.key === 'causer'">
                <Text type="secondary">{{ record.causer }}</Text>
            </template>
            <template v-else-if="column.key === 'created_at'">
                <Text type="secondary">{{ record.created_at }}</Text>
            </template>
        </template>

        <template #expandedRowRender="{ record }">
            <div class="space-y-3">
                <Table
                    v-if="record.changes.length > 0"
                    :columns="changeColumns"
                    :data-source="record.changes"
                    row-key="field"
                    :pagination="false"
                    size="small"
                    class="max-w-2xl"
                >
                    <template #bodyCell="{ column, record: change }">
                        <template v-if="column.key === 'field'">
                            <span class="font-medium">{{ fieldLabel(change.field) }}</span>
                        </template>
                        <template v-else-if="column.key === 'old'">
                            <Text type="secondary" :delete="change.new !== null">{{ display(change.old) }}</Text>
                        </template>
                        <template v-else-if="column.key === 'new'">
                            {{ display(change.new) }}
                        </template>
                    </template>
                </Table>

                <dl v-if="record.details.length > 0" class="max-w-2xl space-y-1 text-sm">
                    <div v-for="detail in record.details" :key="detail.field" class="flex gap-2">
                        <dt class="font-medium">{{ fieldLabel(detail.field) }}:</dt>
                        <dd>{{ display(detail.value) }}</dd>
                    </div>
                </dl>

                <Text v-if="record.changes.length === 0 && record.details.length === 0" type="secondary">
                    {{ record.event === 'updated' ? t('admin.audit.no_changes') : t('admin.audit.no_details') }}
                </Text>

                <div v-if="record.ip || record.user_agent" class="text-xs text-gray-400">
                    <template v-if="record.ip">{{ t('admin.audit.ip') }}: {{ record.ip }}</template>
                    <template v-if="record.ip && record.user_agent"> · </template>
                    <template v-if="record.user_agent">{{ t('admin.audit.browser') }}: {{ record.user_agent }}</template>
                </div>
            </div>
        </template>

        <template #emptyText>{{ t('admin.audit.empty') }}</template>
    </Table>

    <Pagination :paginator="logs" />
</template>
