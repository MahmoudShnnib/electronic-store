<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { btnPrimary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const props = defineProps<{
    settings: Record<string, unknown>;
}>();

interface ContactInfo {
    email?: string;
    phone?: string;
}

const contact = (props.settings['contact_info'] ?? {}) as ContactInfo;

const form = useForm({
    store_name: String(props.settings['store_name'] ?? ''),
    contact_email: contact.email ?? '',
    contact_phone: contact.phone ?? '',
    bank_transfer_details: String(props.settings['bank_transfer_details'] ?? ''),
    invoice_details: String(props.settings['invoice_details'] ?? ''),
    page_about: String(props.settings['page.about'] ?? ''),
    page_contact: String(props.settings['page.contact'] ?? ''),
    page_privacy: String(props.settings['page.privacy-policy'] ?? ''),
    page_terms: String(props.settings['page.terms'] ?? ''),
    page_returns: String(props.settings['page.return-policy'] ?? ''),
    return_window_days: Number(props.settings['return_window_days'] ?? 14),
});

function submit(): void {
    form.transform((data) => ({
        settings: {
            store_name: data.store_name,
            contact_info: { email: data.contact_email, phone: data.contact_phone },
            bank_transfer_details: data.bank_transfer_details,
            invoice_details: data.invoice_details,
            'page.about': data.page_about,
            'page.contact': data.page_contact,
            'page.privacy-policy': data.page_privacy,
            'page.terms': data.page_terms,
            'page.return-policy': data.page_returns,
            return_window_days: data.return_window_days,
        },
    })).post(route('admin.settings.update'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('admin.settings.title')" />

    <PageHeader :title="t('admin.settings.title')" :subtitle="t('admin.settings.subtitle')" />

    <form class="grid max-w-3xl gap-6" @submit.prevent="submit">
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.settings.general') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <Field :label="t('admin.settings.store_name')">
                    <input v-model="form.store_name" type="text" :class="inputClass" />
                </Field>
                <div />
                <Field :label="t('admin.settings.support_email')">
                    <input v-model="form.contact_email" type="email" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.support_phone')">
                    <input v-model="form.contact_phone" type="text" :class="inputClass" />
                </Field>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.settings.payments') }}</h2>
            <div class="grid gap-4">
                <Field :label="t('admin.settings.bank_transfer')" :hint="t('admin.settings.bank_transfer_hint')">
                    <textarea v-model="form.bank_transfer_details" rows="3" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.invoice_footer')" :hint="t('admin.settings.invoice_footer_hint')">
                    <textarea v-model="form.invoice_details" rows="2" :class="inputClass" />
                </Field>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.settings.static_pages') }}</h2>
            <div class="grid gap-4">
                <Field :label="t('admin.settings.about')">
                    <textarea v-model="form.page_about" rows="4" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.contact_page')">
                    <textarea v-model="form.page_contact" rows="3" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.privacy')">
                    <textarea v-model="form.page_privacy" rows="4" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.terms')">
                    <textarea v-model="form.page_terms" rows="4" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.return_window')" :hint="t('admin.settings.return_window_hint')" :error="form.errors['settings.return_window_days']">
                    <input v-model.number="form.return_window_days" type="number" min="0" max="365" :class="inputClass" />
                </Field>
                <Field :label="t('admin.settings.return_policy')">
                    <textarea v-model="form.page_returns" rows="4" :class="inputClass" />
                </Field>
            </div>
        </section>

        <div>
            <button type="submit" :disabled="form.processing" :class="btnPrimary">
                {{ form.processing ? 'Saving…' : 'Save settings' }}
            </button>
        </div>
    </form>
</template>
