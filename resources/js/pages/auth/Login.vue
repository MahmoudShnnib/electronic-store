<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

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

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        dir="ltr"
                        :placeholder="t('auth.email_placeholder')"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <Label for="password">{{ t('auth.password') }}</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" tabindex="5">
                            {{ t('auth.forgot_password') }}
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        dir="ltr"
                        :placeholder="t('auth.password')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <Label for="remember" class="flex items-center gap-3">
                    <Checkbox id="remember" v-model:checked="form.remember" tabindex="3" />
                    <span>{{ t('auth.remember_me') }}</span>
                </Label>

                <Button type="submit" class="mt-4 w-full" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.login') }}
                </Button>

                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                    <span class="h-px flex-1 bg-border"></span>
                    {{ t('auth.or') }}
                    <span class="h-px flex-1 bg-border"></span>
                </div>

                <Button variant="outline" type="button" class="w-full" as-child>
                    <a :href="route('auth.google')">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                fill="#4285F4"
                                d="M23.5 12.27c0-.79-.07-1.55-.2-2.28H12v4.51h6.45a5.52 5.52 0 0 1-2.39 3.62v3h3.87c2.26-2.09 3.57-5.17 3.57-8.85z"
                            />
                            <path
                                fill="#34A853"
                                d="M12 24c3.24 0 5.96-1.07 7.94-2.91l-3.87-3c-1.07.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.72-4.96H1.29v3.09A12 12 0 0 0 12 24z"
                            />
                            <path fill="#FBBC05" d="M5.28 14.28a7.2 7.2 0 0 1 0-4.56V6.63H1.29a12 12 0 0 0 0 10.74l3.99-3.09z" />
                            <path
                                fill="#EA4335"
                                d="M12 4.77c1.76 0 3.34.6 4.59 1.79l3.44-3.44C17.95 1.19 15.23 0 12 0A12 12 0 0 0 1.29 6.63l3.99 3.09C6.22 6.88 8.87 4.77 12 4.77z"
                            />
                        </svg>
                        {{ t('auth.continue_with_google') }}
                    </a>
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.no_account') }}
                <TextLink :href="route('register')" :tabindex="5">{{ t('auth.sign_up') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
