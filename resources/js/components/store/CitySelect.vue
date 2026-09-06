<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Select } from 'ant-design-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface City {
    en: string;
    ar: string;
}

const props = withDefaults(
    defineProps<{
        cities: City[];
        hasError?: boolean;
    }>(),
    { hasError: false },
);

/** English city name — the canonical value stored on orders/addresses. */
const model = defineModel<string>({ required: true });

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

/**
 * Both spellings stay searchable whichever language the page is in: a customer
 * on the Arabic site may still type "Tripoli", and the label they see is the
 * one in their own language.
 */
const options = computed(() =>
    props.cities.map((city) => ({
        value: city.en,
        label: locale.value === 'ar' ? city.ar : city.en,
        search: `${city.en} ${city.ar}`.toLowerCase(),
    })),
);

function filterOption(input: string, option: { search?: string }): boolean {
    return (option.search ?? '').includes(input.trim().toLowerCase());
}
</script>

<template>
    <!-- `''` means nothing picked; AntD wants `undefined` to show the placeholder. -->
    <Select
        :value="model === '' ? undefined : model"
        :options="options"
        :filter-option="filterOption"
        :status="hasError ? 'error' : undefined"
        :placeholder="t('checkout.city')"
        :aria-label="t('checkout.city')"
        show-search
        class="w-full"
        @update:value="(value) => (model = (value as string) ?? '')"
    />
</template>
