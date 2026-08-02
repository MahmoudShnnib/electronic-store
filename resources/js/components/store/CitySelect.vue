<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, MapPin, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface City {
    en: string;
    ar: string;
}

const props = withDefaults(
    defineProps<{
        cities: City[];
        /** Bottom sheet on small screens. Turn off inside dialogs, where
         *  `fixed` resolves against the transformed dialog box instead of
         *  the viewport. */
        sheetOnMobile?: boolean;
        hasError?: boolean;
    }>(),
    { sheetOnMobile: true, hasError: false },
);

/** English city name — the canonical value stored on orders/addresses. */
const model = defineModel<string>({ required: true });

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

const open = ref(false);
const query = ref('');
const root = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

function label(city: City): string {
    return locale.value === 'ar' ? city.ar : city.en;
}

const selected = computed(() => props.cities.find((c) => c.en === model.value) ?? null);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (q === '') {
        return props.cities;
    }

    return props.cities.filter((c) => c.en.toLowerCase().includes(q) || c.ar.includes(q));
});

async function openPanel() {
    open.value = true;
    query.value = '';
    await nextTick();

    // Autofocusing the search field pops the keyboard over the sheet on
    // phones — only do it where a hardware keyboard is likely.
    if (window.matchMedia('(min-width: 640px)').matches) {
        searchInput.value?.focus();
    }
}

function close() {
    open.value = false;
}

function pick(city: City) {
    model.value = city.en;
    close();
}

function onDocumentClick(event: MouseEvent) {
    if (root.value !== null && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close();
    }
}

// Keep the page from scrolling behind the mobile sheet.
watch(open, (isOpen) => {
    if (!props.sheetOnMobile) {
        return;
    }

    const mobile = window.matchMedia('(max-width: 639px)').matches;
    document.documentElement.style.overflow = isOpen && mobile ? 'hidden' : '';
});

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    document.documentElement.style.overflow = '';
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2 text-sm transition"
            :class="hasError ? 'border-red-500' : 'border-input'"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open ? close() : openPanel()"
        >
            <span class="flex min-w-0 items-center gap-2">
                <MapPin class="h-4 w-4 shrink-0 text-muted-foreground" />
                <span v-if="selected" class="truncate">{{ label(selected) }}</span>
                <span v-else class="truncate text-muted-foreground">{{ t('checkout.select_city') }}</span>
            </span>
            <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="open ? 'rotate-180' : ''" />
        </button>

        <!-- Mobile backdrop (sheet mode only) -->
        <div v-if="open && sheetOnMobile" class="fixed inset-0 z-[60] bg-black/50 sm:hidden" @click.stop="close" />

        <!-- Panel: bottom sheet on mobile (sheet mode), anchored dropdown from sm up -->
        <div
            v-if="open"
            class="z-[70] flex flex-col overflow-hidden border border-border bg-card shadow-xl"
            :class="
                sheetOnMobile
                    ? 'fixed inset-x-0 bottom-0 max-h-[75vh] rounded-t-2xl sm:absolute sm:inset-x-0 sm:top-full sm:bottom-auto sm:mt-2 sm:max-h-80 sm:rounded-xl'
                    : 'absolute inset-x-0 top-full mt-2 max-h-80 rounded-xl'
            "
            role="listbox"
        >
            <!-- Sheet header (mobile only) -->
            <div v-if="sheetOnMobile" class="flex items-center justify-between border-b border-border px-4 py-3 sm:hidden">
                <span class="font-semibold">{{ t('checkout.select_city') }}</span>
                <button type="button" class="rounded-md p-1.5 text-muted-foreground hover:bg-muted" :aria-label="t('common.close')" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Search -->
            <div class="border-b border-border p-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground ltr:left-3 rtl:right-3" />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        :placeholder="t('checkout.search_city')"
                        class="w-full rounded-lg border border-input bg-background py-2 text-sm ltr:pl-9 ltr:pr-3 rtl:pr-9 rtl:pl-3 focus:outline-none focus:ring-1 focus:ring-foreground/30"
                    />
                </div>
            </div>

            <!-- Cities -->
            <ul class="grid grid-cols-2 gap-1.5 overflow-y-auto p-2 sm:block sm:space-y-0.5">
                <li v-for="city in filtered" :key="city.en">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm transition sm:border-0"
                        :class="
                            model === city.en
                                ? 'bg-neutral-100 font-semibold dark:bg-neutral-800'
                                : 'border border-border hover:bg-muted sm:border-0'
                        "
                        role="option"
                        :aria-selected="model === city.en"
                        @click="pick(city)"
                    >
                        <span class="truncate">{{ label(city) }}</span>
                        <Check v-if="model === city.en" class="h-4 w-4 shrink-0" />
                    </button>
                </li>
                <li v-if="filtered.length === 0" class="col-span-2 px-3 py-6 text-center text-sm text-muted-foreground">
                    {{ t('common.no_results') }}
                </li>
            </ul>
        </div>
    </div>
</template>
