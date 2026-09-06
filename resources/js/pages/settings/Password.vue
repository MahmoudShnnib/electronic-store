<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, Form, FormItem, InputPassword, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

const { Title, Text } = Typography;

const { t } = useI18n();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: Record<string, string>) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
            }

            if (errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <Head :title="t('settings.password_title')" />

    <SettingsLayout>
        <Card>
            <Title :level="2" class="!mb-1 !text-base !font-semibold">{{ t('settings.password_title') }}</Title>
            <Text class="!mb-5 !block !text-sm opacity-60">{{ t('settings.password_description') }}</Text>

            <Form layout="vertical" @submit.prevent="updatePassword">
                <FormItem
                    :label="t('settings.current_password')"
                    :validate-status="form.errors.current_password ? 'error' : undefined"
                    :help="form.errors.current_password"
                >
                    <InputPassword v-model:value="form.current_password" autocomplete="current-password" dir="ltr" />
                </FormItem>

                <FormItem
                    :label="t('settings.new_password')"
                    :validate-status="form.errors.password ? 'error' : undefined"
                    :help="form.errors.password"
                >
                    <InputPassword v-model:value="form.password" autocomplete="new-password" dir="ltr" />
                </FormItem>

                <FormItem
                    :label="t('settings.confirm_password')"
                    :validate-status="form.errors.password_confirmation ? 'error' : undefined"
                    :help="form.errors.password_confirmation"
                >
                    <InputPassword v-model:value="form.password_confirmation" autocomplete="new-password" dir="ltr" />
                </FormItem>

                <div class="flex items-center gap-3">
                    <Button type="primary" html-type="submit" :loading="form.processing">{{ t('settings.save_password') }}</Button>
                    <Transition name="fade">
                        <Text v-if="form.recentlySuccessful" class="!text-sm opacity-60">{{ t('settings.saved') }}</Text>
                    </Transition>
                </div>
            </Form>
        </Card>
    </SettingsLayout>
</template>
