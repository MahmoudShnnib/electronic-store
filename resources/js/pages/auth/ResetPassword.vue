<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Form, FormItem, Input, InputPassword } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * Autofocus is a desktop-only convenience: on a phone it throws the on-screen
 * keyboard up over the page before the person has read it.
 */
const autofocus = useMediaQuery('(min-width: 768px) and (pointer: fine)');

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.reset_title')" :description="t('auth.reset_description')">
        <Head :title="t('auth.reset_title')" />

        <Form layout="vertical" @submit.prevent="submit">
            <FormItem :label="t('auth.email')" :validate-status="form.errors.email ? 'error' : undefined" :help="form.errors.email">
                <Input v-model:value="form.email" type="email" size="large" autocomplete="email" dir="ltr" readonly />
            </FormItem>

            <FormItem :label="t('auth.password')" :validate-status="form.errors.password ? 'error' : undefined" :help="form.errors.password">
                <InputPassword
                    v-model:value="form.password"
                    size="large"
                    :autofocus="autofocus"
                    autocomplete="new-password"
                    dir="ltr"
                    :placeholder="t('auth.password')"
                />
            </FormItem>

            <FormItem
                :label="t('auth.confirm_password')"
                :validate-status="form.errors.password_confirmation ? 'error' : undefined"
                :help="form.errors.password_confirmation"
            >
                <InputPassword
                    v-model:value="form.password_confirmation"
                    size="large"
                    autocomplete="new-password"
                    dir="ltr"
                    :placeholder="t('auth.confirm_password')"
                />
            </FormItem>

            <Button type="primary" size="large" block html-type="submit" :loading="form.processing">{{ t('auth.reset_password') }}</Button>
        </Form>
    </AuthLayout>
</template>
