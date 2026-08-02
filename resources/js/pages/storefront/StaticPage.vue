<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Mail, Phone } from 'lucide-vue-next';

defineOptions({ layout: StoreLayout });

defineProps<{
    page: string;
    title: string;
    content: string | null;
    contactInfo: { email?: string; phone?: string } | null;
}>();
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mx-auto max-w-3xl py-4">
            <h1 class="text-3xl font-bold">{{ title }}</h1>

            <div class="prose dark:prose-invert mt-6 max-w-none whitespace-pre-line text-muted-foreground">
                {{ content ?? '—' }}
            </div>

            <div v-if="contactInfo" class="mt-8 space-y-3">
                <a
                    v-if="contactInfo.email"
                    :href="`mailto:${contactInfo.email}`"
                    class="flex items-center gap-3 rounded-xl border border-border bg-card p-4 transition hover:border-black dark:hover:border-white"
                >
                    <Mail class="h-5 w-5 text-black dark:text-white" />
                    <span class="font-medium">{{ contactInfo.email }}</span>
                </a>
                <a
                    v-if="contactInfo.phone"
                    :href="`tel:${contactInfo.phone}`"
                    class="flex items-center gap-3 rounded-xl border border-border bg-card p-4 transition hover:border-black dark:hover:border-white"
                >
                    <Phone class="h-5 w-5 text-black dark:text-white" />
                    <span class="font-medium" dir="ltr">{{ contactInfo.phone }}</span>
                </a>
            </div>
        </div>
    </div>
</template>
