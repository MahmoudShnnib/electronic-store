<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Form, FormItem, Input, InputPassword } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * Autofocus is a desktop-only convenience: on a phone it throws the on-screen
 * keyboard up over the page before the person has read it.
 */
const autofocus = useMediaQuery('(min-width: 768px) and (pointer: fine)');

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase :title="t('auth.register_title')" :description="t('auth.register_description')">
        <Head :title="t('auth.register_title')" />

        <Form layout="vertical" @submit.prevent="submit">
            <FormItem :label="t('auth.name')" :validate-status="form.errors.name ? 'error' : undefined" :help="form.errors.name">
                <Input v-model:value="form.name" size="large" :autofocus="autofocus" autocomplete="name" :placeholder="t('auth.name_placeholder')" />
            </FormItem>

            <FormItem :label="t('auth.email')" :validate-status="form.errors.email ? 'error' : undefined" :help="form.errors.email">
                <Input v-model:value="form.email" type="email" size="large" autocomplete="email" dir="ltr" :placeholder="t('auth.email_placeholder')" />
            </FormItem>

            <FormItem :label="t('auth.password')" :validate-status="form.errors.password ? 'error' : undefined" :help="form.errors.password">
                <InputPassword v-model:value="form.password" size="large" autocomplete="new-password" dir="ltr" :placeholder="t('auth.password')" />
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

            <Button type="primary" size="large" block html-type="submit" :loading="form.processing">{{ t('auth.create_account') }}</Button>

            <p class="mt-6 text-center text-sm opacity-65">
                {{ t('auth.have_account') }}
                <Link :href="route('login')" class="font-medium text-[#0F9D58] hover:underline">{{ t('auth.login') }}</Link>
            </p>
        </Form>
    </AuthBase>
</template>
