<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Alert, Button } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout :title="t('auth.verify_title')" :description="t('auth.verify_description')">
        <Head :title="t('auth.verify_title')" />

        <Alert v-if="status === 'verification-link-sent'" type="success" show-icon class="!mb-4" :message="t('auth.verify_sent')" />

        <form class="space-y-4 text-center" @submit.prevent="submit">
            <Button size="large" block html-type="submit" :loading="form.processing">{{ t('auth.resend_verification') }}</Button>

            <Link :href="route('logout')" method="post" as="button" class="mx-auto block text-sm opacity-65 hover:underline">
                {{ t('auth.logout') }}
            </Link>
        </form>
    </AuthLayout>
</template>
