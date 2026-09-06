<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Alert, Button, Form, FormItem, Input } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * Autofocus is a desktop-only convenience: on a phone it throws the on-screen
 * keyboard up over the page before the person has read it.
 */
const autofocus = useMediaQuery('(min-width: 768px) and (pointer: fine)');

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout :title="t('auth.forgot_title')" :description="t('auth.forgot_description')">
        <Head :title="t('auth.forgot_title')" />

        <Alert v-if="status" type="success" show-icon class="!mb-4" :message="status" />

        <Form layout="vertical" @submit.prevent="submit">
            <FormItem :label="t('auth.email')" :validate-status="form.errors.email ? 'error' : undefined" :help="form.errors.email">
                <Input
                    v-model:value="form.email"
                    type="email"
                    size="large"
                    :autofocus="autofocus"
                    autocomplete="email"
                    spellcheck="false"
                    dir="ltr"
                    :placeholder="t('auth.email_placeholder')"
                />
            </FormItem>

            <Button type="primary" size="large" block html-type="submit" :loading="form.processing">{{ t('auth.send_reset_link') }}</Button>

            <p class="mt-6 text-center text-sm opacity-65">
                {{ t('auth.return_to') }}
                <Link :href="route('login')" class="font-medium text-[#0F9D58] hover:underline">{{ t('auth.login') }}</Link>
            </p>
        </Form>
    </AuthLayout>
</template>
