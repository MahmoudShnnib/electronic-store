<script setup lang="ts">
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t, te } = useI18n();

/**
 * Activity rows store machine keys (`po_received`) and raw DB columns
 * (`price_cents`). Both were rendered verbatim, so the page stayed English on
 * the Arabic site and read like debug output. Unknown keys fall back to a
 * humanized version of the key rather than showing a missing-translation path.
 */
function eventLabel(event: string | null): string {
    if (!event) return '—';

    return te(`admin.audit.events.${event}`) ? t(`admin.audit.events.${event}`) : humanize(event);
}

function fieldLabel(field: string): string {
    return te(`admin.audit.fields.${field}`) ? t(`admin.audit.fields.${field}`) : humanize(field);
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
    causer: string;
    properties: Record<string, unknown>;
    changes: { field: string; old: unknown; new: unknown }[];
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

function toggle(id: number): void {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
    expanded.value = new Set(expanded.value);
}

function pretty(properties: Record<string, unknown>): string {
    return JSON.stringify(properties, null, 2);
}
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

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950/50">
                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="w-8 px-3 py-2.5" />
                    <th class="px-3 py-2.5 text-start">{{ t('admin.audit.description') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.audit.event') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.audit.subject') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.common.user') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.common.date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <template v-for="log in logs.data" :key="log.id">
                    <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50" @click="toggle(log.id)">
                        <td class="px-3 py-2.5 text-gray-400">
                            <ChevronDown v-if="expanded.has(log.id)" class="h-4 w-4" />
                            <ChevronRight v-else class="h-4 w-4" />
                        </td>
                        <td class="px-3 py-2.5 text-gray-800 dark:text-gray-200">
                            {{ te(`admin.audit.events.${log.description}`) ? t(`admin.audit.events.${log.description}`) : log.description }}
                        </td>
                        <td class="px-3 py-2.5 text-gray-500">{{ eventLabel(log.event) }}</td>
                        <td class="px-3 py-2.5 text-gray-500">
                            <template v-if="log.subject_type">{{ log.subject_type }} #{{ log.subject_id }}</template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-3 py-2.5 text-gray-500">{{ log.causer }}</td>
                        <td class="px-3 py-2.5 text-gray-400">{{ log.created_at }}</td>
                    </tr>
                    <tr v-if="expanded.has(log.id)">
                        <td colspan="6" class="bg-gray-50 px-6 py-3 dark:bg-gray-950/50">
                            <table v-if="log.changes.length > 0" class="w-full max-w-2xl text-xs">
                                <thead>
                                    <tr class="text-start text-gray-400">
                                        <th class="py-1 pe-4 text-start font-medium">{{ t('admin.audit.description') }}</th>
                                        <th class="py-1 pe-4 text-start font-medium">{{ t('admin.audit.before') }}</th>
                                        <th class="py-1 text-start font-medium">{{ t('admin.audit.after') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="change in log.changes" :key="change.field" class="border-t border-gray-200 dark:border-gray-800">
                                        <td class="py-1.5 pe-4 font-medium text-gray-600 dark:text-gray-300">{{ fieldLabel(change.field) }}</td>
                                        <td class="py-1.5 pe-4 text-gray-500 line-through decoration-red-400/60">{{ display(change.old) }}</td>
                                        <td class="py-1.5 text-gray-800 dark:text-gray-100">{{ display(change.new) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <pre v-else class="max-h-64 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{
                                pretty(log.properties)
                            }}</pre>
                        </td>
                    </tr>
                </template>
                <tr v-if="logs.data.length === 0">
                    <td colspan="6" class="px-3 py-10 text-center text-gray-400">{{ t('admin.audit.empty') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :links="logs.links" :total="logs.total" :from="logs.from" :to="logs.to" />
</template>
