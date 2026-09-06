<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, Typography } from 'ant-design-vue';
import { Mail, Phone } from 'lucide-vue-next';

defineOptions({ layout: StoreLayout });

defineProps<{
    page: string;
    title: string;
    content: string | null;
    contactInfo: { email?: string; phone?: string } | null;
}>();

const { Title, Paragraph } = Typography;
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mx-auto max-w-3xl py-4">
            <Title :level="1" class="!text-3xl !font-bold">{{ title }}</Title>

            <Paragraph class="!mt-6 whitespace-pre-line !leading-relaxed opacity-75">{{ content ?? '—' }}</Paragraph>

            <div v-if="contactInfo" class="mt-8 space-y-3">
                <a v-if="contactInfo.email" :href="`mailto:${contactInfo.email}`" class="block">
                    <Card size="small" hoverable :body-style="{ padding: '1rem' }">
                        <div class="flex items-center gap-3">
                            <Mail class="h-5 w-5 text-[#0F9D58]" />
                            <span class="font-medium">{{ contactInfo.email }}</span>
                        </div>
                    </Card>
                </a>
                <a v-if="contactInfo.phone" :href="`tel:${contactInfo.phone}`" class="block">
                    <Card size="small" hoverable :body-style="{ padding: '1rem' }">
                        <div class="flex items-center gap-3">
                            <Phone class="h-5 w-5 text-[#0F9D58]" />
                            <span class="font-medium" dir="ltr">{{ contactInfo.phone }}</span>
                        </div>
                    </Card>
                </a>
            </div>
        </div>
    </div>
</template>
