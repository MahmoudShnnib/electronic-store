<script setup lang="ts">
import DeleteUser from '@/components/DeleteUser.vue';
import StoreLayout from '@/layouts/StoreLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type SharedData, type User } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Form, FormItem, Input, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const { Title, Text } = Typography;

const { t } = useI18n();

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone ?? '',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('settings.profile_title')" />

    <SettingsLayout>
        <Card>
            <Title :level="2" class="!mb-1 !text-base !font-semibold">{{ t('settings.profile_title') }}</Title>
            <Text class="!mb-5 !block !text-sm opacity-60">{{ t('settings.profile_description') }}</Text>

            <Form layout="vertical" @submit.prevent="submit">
                <FormItem :label="t('settings.name')" required :validate-status="form.errors.name ? 'error' : undefined" :help="form.errors.name">
                    <Input v-model:value="form.name" autocomplete="name" :placeholder="t('settings.name_placeholder')" />
                </FormItem>

                <FormItem :label="t('settings.email')" required :validate-status="form.errors.email ? 'error' : undefined" :help="form.errors.email">
                    <Input v-model:value="form.email" type="email" autocomplete="username" dir="ltr" />
                </FormItem>

                <FormItem :label="t('settings.phone')" :validate-status="form.errors.phone ? 'error' : undefined" :help="form.errors.phone">
                    <Input v-model:value="form.phone" type="tel" autocomplete="tel" dir="ltr" />
                </FormItem>

                <Alert v-if="mustVerifyEmail && !user.email_verified_at" type="warning" show-icon class="!mb-4">
                    <template #message>
                        {{ t('settings.unverified') }}
                        <Link :href="route('verification.send')" method="post" as="button" class="font-medium underline">
                            {{ t('settings.resend_verification') }}
                        </Link>
                    </template>
                </Alert>

                <Alert v-if="status === 'verification-link-sent'" type="success" show-icon class="!mb-4" :message="t('settings.verification_sent')" />

                <div class="flex items-center gap-3">
                    <Button type="primary" html-type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                    <Transition name="fade">
                        <Text v-if="form.recentlySuccessful" class="!text-sm opacity-60">{{ t('settings.saved') }}</Text>
                    </Transition>
                </div>
            </Form>
        </Card>

        <DeleteUser />
    </SettingsLayout>
</template>
