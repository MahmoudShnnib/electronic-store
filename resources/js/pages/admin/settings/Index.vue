<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, Input, InputNumber, Textarea } from 'ant-design-vue';
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

    <!-- Two columns on xl: General + Payments are short, Static pages is six
         fields tall, so pairing them balances instead of leaving half the page
         empty under a single narrow column. -->
    <form class="grid max-w-3xl gap-6 xl:max-w-6xl xl:grid-cols-2 xl:items-start" @submit.prevent="submit">
        <div class="grid gap-6">
            <Card :title="t('admin.settings.general')" size="small">
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <Field :label="t('admin.settings.store_name')">
                        <Input v-model:value="form.store_name" />
                    </Field>
                    <div />
                    <Field :label="t('admin.settings.support_email')">
                        <Input v-model:value="form.contact_email" type="email" />
                    </Field>
                    <Field :label="t('admin.settings.support_phone')">
                        <Input v-model:value="form.contact_phone" />
                    </Field>
                </div>
            </Card>

            <Card :title="t('admin.settings.payments')" size="small">
                <Field :label="t('admin.settings.bank_transfer')" :hint="t('admin.settings.bank_transfer_hint')">
                    <Textarea v-model:value="form.bank_transfer_details" :rows="3" />
                </Field>
                <Field :label="t('admin.settings.invoice_footer')" :hint="t('admin.settings.invoice_footer_hint')">
                    <Textarea v-model:value="form.invoice_details" :rows="2" />
                </Field>
            </Card>
        </div>

        <Card :title="t('admin.settings.static_pages')" size="small">
            <Field :label="t('admin.settings.about')">
                <Textarea v-model:value="form.page_about" :rows="4" />
            </Field>
            <Field :label="t('admin.settings.contact_page')">
                <Textarea v-model:value="form.page_contact" :rows="3" />
            </Field>
            <Field :label="t('admin.settings.privacy')">
                <Textarea v-model:value="form.page_privacy" :rows="4" />
            </Field>
            <Field :label="t('admin.settings.terms')">
                <Textarea v-model:value="form.page_terms" :rows="4" />
            </Field>
            <Field
                :label="t('admin.settings.return_window')"
                :hint="t('admin.settings.return_window_hint')"
                :error="form.errors['settings.return_window_days']"
            >
                <InputNumber v-model:value="form.return_window_days" :min="0" :max="365" class="w-full" />
            </Field>
            <Field :label="t('admin.settings.return_policy')">
                <Textarea v-model:value="form.page_returns" :rows="4" />
            </Field>
        </Card>

        <div class="xl:col-span-2">
            <Button type="primary" html-type="submit" :loading="form.processing">
                {{ form.processing ? t('admin.common.saving') : t('admin.common.save') }}
            </Button>
        </div>
    </form>
</template>
