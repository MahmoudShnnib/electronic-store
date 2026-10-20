<script setup lang="ts">
import { MessageCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * A customer's phone as one-tap actions: the number calls, the icon opens a
 * WhatsApp chat (optionally with a message ready) — confirming a cash-on-
 * delivery order is the shop's most common task, and copying numbers by hand
 * was the slow part.
 */
const props = defineProps<{ phone: string | null | undefined; message?: string }>();

const { t } = useI18n();

/**
 * Libyan mobiles as 218XXXXXXXXX, however they were typed (0912…, +218 91…,
 * 00218…, Arabic digits) — wa.me only takes the international form. Null for
 * anything that isn't a Libyan mobile; the call link then uses the raw number.
 */
const international = computed<string | null>(() => {
    const digits = (props.phone ?? '').replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).replace(/\D/g, '');
    const local = digits.slice(-9);

    return /^9\d{8}$/.test(local) ? `218${local}` : null;
});

const telHref = computed(() => `tel:${international.value ? `+${international.value}` : (props.phone ?? '').replace(/[^\d+]/g, '')}`);

const whatsappHref = computed(() =>
    international.value ? `https://wa.me/${international.value}${props.message ? `?text=${encodeURIComponent(props.message)}` : ''}` : null,
);
</script>

<template>
    <span v-if="phone" class="inline-flex items-center gap-2">
        <a :href="telHref" class="admin-link hover:underline" :title="t('admin.common.call')"
            ><bdi dir="ltr">{{ phone }}</bdi></a
        >
        <a
            v-if="whatsappHref"
            :href="whatsappHref"
            target="_blank"
            rel="noopener noreferrer"
            class="admin-whatsapp-link"
            :aria-label="t('admin.common.whatsapp')"
            :title="t('admin.common.whatsapp')"
        >
            <MessageCircle class="h-4 w-4" aria-hidden="true" />
        </a>
    </span>
    <span v-else>—</span>
</template>
