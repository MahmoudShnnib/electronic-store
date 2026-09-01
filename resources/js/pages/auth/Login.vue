<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Alert, Button, Checkbox, Divider, Form, FormItem, Input, InputPassword, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * Autofocus is a desktop-only convenience: on a phone it throws the on-screen
 * keyboard up over the page before the person has read it.
 */
const autofocus = useMediaQuery('(min-width: 768px) and (pointer: fine)');

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { Text } = Typography;

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase :title="t('auth.login_title')" :description="t('auth.login_description')">
        <Head :title="t('auth.login')" />

        <Alert v-if="status" type="success" show-icon class="!mb-4" :message="status" />

        <Form layout="vertical" @submit.prevent="submit">
            <FormItem :label="t('auth.email')" :validate-status="form.errors.email ? 'error' : undefined" :help="form.errors.email">
                <Input
                    v-model:value="form.email"
                    type="email"
                    size="large"
                    :autofocus="autofocus"
                    autocomplete="email"
                    dir="ltr"
                    :placeholder="t('auth.email_placeholder')"
                />
            </FormItem>

            <FormItem class="store-label-wide" :validate-status="form.errors.password ? 'error' : undefined" :help="form.errors.password">
                <template #label>
                    <span class="flex w-full items-center justify-between gap-2">
                        {{ t('auth.password') }}
                        <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-normal text-[#0F9D58] hover:underline">
                            {{ t('auth.forgot_password') }}
                        </Link>
                    </span>
                </template>
                <InputPassword
                    v-model:value="form.password"
                    size="large"
                    autocomplete="current-password"
                    dir="ltr"
                    :placeholder="t('auth.password')"
                />
            </FormItem>

            <FormItem>
                <Checkbox v-model:checked="form.remember">{{ t('auth.remember_me') }}</Checkbox>
            </FormItem>

            <Button type="primary" size="large" block html-type="submit" :loading="form.processing">{{ t('auth.login') }}</Button>

            <Divider plain class="!my-5"><Text class="!text-xs opacity-55">{{ t('auth.or') }}</Text></Divider>

            <Button size="large" block :href="route('auth.google')">
                <template #icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M23.5 12.27c0-.79-.07-1.55-.2-2.28H12v4.51h6.45a5.52 5.52 0 0 1-2.39 3.62v3h3.87c2.26-2.09 3.57-5.17 3.57-8.85z" />
                        <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.91l-3.87-3c-1.07.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.72-4.96H1.29v3.09A12 12 0 0 0 12 24z" />
                        <path fill="#FBBC05" d="M5.28 14.28a7.2 7.2 0 0 1 0-4.56V6.63H1.29a12 12 0 0 0 0 10.74l3.99-3.09z" />
                        <path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.59 1.79l3.44-3.44C17.95 1.19 15.23 0 12 0A12 12 0 0 0 1.29 6.63l3.99 3.09C6.22 6.88 8.87 4.77 12 4.77z" />
                    </svg>
                </template>
                {{ t('auth.continue_with_google') }}
            </Button>

            <p class="mt-6 text-center text-sm opacity-65">
                {{ t('auth.no_account') }}
                <Link :href="route('register')" class="font-medium text-[#0F9D58] hover:underline">{{ t('auth.sign_up') }}</Link>
            </p>
        </Form>
    </AuthBase>
</template>
