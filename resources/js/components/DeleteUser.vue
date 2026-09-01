<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import { useForm } from '@inertiajs/vue3';
import { Alert, Button, Card, Form, FormItem, InputPassword, Modal, Typography } from 'ant-design-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Autofocus is a desktop-only convenience: on a phone it throws the on-screen
 * keyboard up over the page before the person has read it.
 */
const autofocus = useMediaQuery('(min-width: 768px) and (pointer: fine)');

const { Title, Text } = Typography;

const { t } = useI18n();

const open = ref(false);

const form = useForm({
    password: '',
});

function deleteUser() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => form.reset(),
    });
}

function closeModal() {
    open.value = false;
    form.clearErrors();
    form.reset();
}
</script>

<template>
    <Card>
        <Title :level="2" class="!mb-1 !text-base !font-semibold">{{ t('settings.delete_title') }}</Title>
        <Text class="!block !text-sm opacity-60">{{ t('settings.delete_description') }}</Text>

        <Alert type="error" show-icon class="!mt-4" :message="t('settings.delete_warning')" />

        <Button danger class="!mt-4" @click="open = true">{{ t('settings.delete_button') }}</Button>

        <Modal :open="open" :title="t('settings.delete_confirm_title')" :footer="null" @cancel="closeModal">
            <Form layout="vertical" @submit.prevent="deleteUser">
                <Text class="!mb-4 !block opacity-70">{{ t('settings.delete_confirm_body') }}</Text>

                <FormItem :label="t('auth.password')" :validate-status="form.errors.password ? 'error' : undefined" :help="form.errors.password">
                    <InputPassword v-model:value="form.password" :autofocus="autofocus" dir="ltr" />
                </FormItem>

                <div class="flex justify-end gap-2">
                    <Button @click="closeModal">{{ t('common.cancel') }}</Button>
                    <Button type="primary" danger html-type="submit" :loading="form.processing">{{ t('settings.delete_button') }}</Button>
                </div>
            </Form>
        </Modal>
    </Card>
</template>
