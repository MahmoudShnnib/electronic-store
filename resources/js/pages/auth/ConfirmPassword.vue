<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Form, FormItem, InputPassword } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * Autofocus is a desktop-only convenience: on a phone it throws the on-screen
 * keyboard up over the page before the person has read it.
 */
const autofocus = useMediaQuery('(min-width: 768px) and (pointer: fine)');

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.confirm_title')" :description="t('auth.confirm_description')">
        <Head :title="t('auth.confirm_title')" />

        <Form layout="vertical" @submit.prevent="submit">
            <FormItem :label="t('auth.password')" :validate-status="form.errors.password ? 'error' : undefined" :help="form.errors.password">
                <InputPassword v-model:value="form.password" size="large" :autofocus="autofocus" autocomplete="current-password" dir="ltr" />
            </FormItem>

            <Button type="primary" size="large" block html-type="submit" :loading="form.processing">{{ t('auth.confirm_title') }}</Button>
        </Form>
    </AuthLayout>
</template>
