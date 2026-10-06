<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button, Card, Input, Textarea, Upload } from 'ant-design-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const props = defineProps<{
    settings: Record<string, unknown>;
    /** Already converted to a black-on-white data URI; null when unset. */
    receiptLogo: string | null;
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
});

const uploadingLogo = ref(false);

/**
 * The logo posts on its own, not with the rest of the form.
 *
 * It is a file, and the settings form is a JSON map; more to the point the
 * server converts the image on upload — reduces it to one bit and flips it if
 * needed — so what comes back is not what was sent, and a normal round-trip
 * would fight that.
 */
function uploadLogo(file: File): boolean {
    uploadingLogo.value = true;

    router.post(
        route('admin.settings.receipt-logo.store'),
        { logo: file },
        { preserveScroll: true, forceFormData: true, onFinish: () => (uploadingLogo.value = false) },
    );

    // Returning false keeps AntD from trying to upload it itself.
    return false;
}

function removeLogo(): void {
    router.delete(route('admin.settings.receipt-logo.destroy'), { preserveScroll: true });
}

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

                <Field :label="t('admin.settings.receipt_logo')" :hint="t('admin.settings.receipt_logo_hint')">
                    <div class="flex items-center gap-3">
                        <!-- Shown on the same white the paper is, because that is
                             exactly what will be printed — no colour, no greys. -->
                        <img v-if="receiptLogo" :src="receiptLogo" alt="" class="h-16 w-auto rounded border border-ad-line bg-white p-1" />
                        <Upload :show-upload-list="false" accept="image/*" :before-upload="uploadLogo">
                            <Button :loading="uploadingLogo">{{ t('admin.settings.receipt_logo_upload') }}</Button>
                        </Upload>
                        <Button v-if="receiptLogo" danger type="text" @click="removeLogo">{{ t('admin.common.remove') }}</Button>
                    </div>
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
