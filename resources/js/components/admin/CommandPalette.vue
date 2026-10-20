<script setup lang="ts">
/**
 * Ctrl/Cmd+K: jump to any dashboard page, run a quick action, or open an
 * order, product or customer by typing part of its number, name, SKU, email
 * or phone.
 *
 * Pages and actions are the same permission-filtered list the sidebar shows
 * (`useAdminNav`), matched locally in both languages — typing "orders" finds
 * الطلبات under Arabic and the other way round. Records come from
 * `admin.search`, which only searches what the user may open.
 *
 * Keyboard: ↑/↓ move, Enter opens, Esc closes (AntD Modal). The list is one
 * flat index across the groups so the arrows never stop at a heading.
 */
import { useAdminNav, type NavLink } from '@/composables/useAdminNav';
import { router, usePage } from '@inertiajs/vue3';
import { Input, Modal, Spin } from 'ant-design-vue';
import { CornerDownLeft, FileText, Package, Search, ShoppingCart, User } from 'lucide-vue-next';
import { computed, nextTick, ref, watch, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

export interface PaletteCommand {
    key: string;
    label: string;
    /** Extra words that should find it ("dark", "language"). */
    keywords?: string;
    icon: Component;
    run: () => void;
}

const props = defineProps<{
    open: boolean;
    /** Actions that are not pages: theme, language, POS… — supplied by the layout. */
    commands: PaletteCommand[];
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const { t, locale } = useI18n();
const page = usePage();
const { allLinks, quickActions } = useAdminNav();

interface OrderHit {
    id: number;
    number: string;
    customer: string;
    status_label: string;
    total_formatted: string;
    created_at: string;
    url: string;
}

interface ProductHit {
    id: number;
    name: string;
    name_ar: string | null;
    sku: string;
    status_label: string;
    price_formatted: string;
    url: string;
}

interface CustomerHit {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    url: string;
}

interface Row {
    id: string;
    icon: Component;
    label: string;
    sub?: string;
    meta?: string;
    /** Content typed by users (names, emails): direction resolved per row. */
    userText?: boolean;
    select: () => void;
}

interface Section {
    key: string;
    title: string;
    rows: Row[];
}

const query = ref('');
const activeIndex = ref(0);
const loading = ref(false);
const results = ref<{ orders: OrderHit[] | null; products: ProductHit[] | null; customers: CustomerHit[] | null } | null>(null);

const inputRef = ref<InstanceType<typeof Input> | null>(null);
const listRef = ref<HTMLElement | null>(null);

function close(): void {
    emit('update:open', false);
}

function visit(href: string): void {
    close();
    router.visit(href);
}

/*
 * Arabic folding: drop diacritics and tatweel, and treat the alef, yaa and
 * taa-marbuta variants alike, so "اداره" finds "إدارة". Latin is lower-cased.
 */
function fold(text: string): string {
    return text
        .toLowerCase()
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .trim();
}

const terms = computed(() => fold(query.value).split(/\s+/).filter(Boolean));

function matches(haystack: string): boolean {
    const folded = fold(haystack);

    return terms.value.every((term) => folded.includes(term));
}

/** Both languages' labels, so either finds the page whatever the UI language. */
function bilingual(labelKey: string): string {
    return `${t(labelKey, {}, { locale: 'ar' })} ${t(labelKey, {}, { locale: 'en' })}`;
}

const pageRows = computed<Row[]>(() =>
    allLinks.value
        .filter((l: NavLink & { group: string }) => terms.value.length === 0 || matches(`${bilingual(l.labelKey)} ${l.group}`))
        .map((l) => ({
            id: `page:${l.key}`,
            icon: l.icon,
            label: l.label,
            sub: l.group || undefined,
            select: () => visit(l.href),
        })),
);

const actionRows = computed<Row[]>(() => [
    ...quickActions.value
        .filter((a) => terms.value.length === 0 || matches(bilingual(a.labelKey)))
        .map((a) => ({ id: `action:${a.key}`, icon: a.icon, label: a.label, select: () => visit(a.href) })),
    ...props.commands
        .filter((c) => terms.value.length === 0 || matches(`${c.label} ${c.keywords ?? ''}`))
        .map((c) => ({
            id: `cmd:${c.key}`,
            icon: c.icon,
            label: c.label,
            select: () => {
                close();
                c.run();
            },
        })),
]);

const productName = (p: ProductHit) => (locale.value === 'ar' && p.name_ar ? p.name_ar : p.name);

const sections = computed<Section[]>(() => {
    const list: Section[] = [];
    const r = results.value;

    if (r?.orders?.length) {
        list.push({
            key: 'orders',
            title: t('admin.nav.orders'),
            rows: r.orders.map((o) => ({
                id: `order:${o.id}`,
                icon: ShoppingCart,
                label: o.number,
                sub: `${o.customer} · ${o.status_label}`,
                meta: o.total_formatted,
                userText: true,
                select: () => visit(o.url),
            })),
        });
    }

    if (r?.products?.length) {
        list.push({
            key: 'products',
            title: t('admin.nav.products'),
            rows: r.products.map((p) => ({
                id: `product:${p.id}`,
                icon: Package,
                label: productName(p),
                sub: `${p.sku} · ${p.status_label}`,
                meta: p.price_formatted,
                userText: true,
                select: () => visit(p.url),
            })),
        });
    }

    if (r?.customers?.length) {
        list.push({
            key: 'customers',
            title: t('admin.nav.customers'),
            rows: r.customers.map((c) => ({
                id: `customer:${c.id}`,
                icon: User,
                label: c.name,
                sub: [c.email, c.phone].filter(Boolean).join(' · '),
                userText: true,
                select: () => visit(c.url),
            })),
        });
    }

    // While searching, records come first: that is what a typed number is for.
    if (actionRows.value.length) {
        list.push({ key: 'actions', title: t('admin.palette.actions'), rows: actionRows.value });
    }

    if (pageRows.value.length) {
        list.push({ key: 'pages', title: t('admin.palette.pages'), rows: pageRows.value });
    }

    return list;
});

const flat = computed(() => sections.value.flatMap((s) => s.rows));

const indexOf = (row: Row) => flat.value.indexOf(row);

const canSearchRecords = computed(() => query.value.trim().length >= 2);

const noResults = computed(() => terms.value.length > 0 && flat.value.length === 0 && !loading.value);

/* --- Record search -------------------------------------------------------- */

let timer: ReturnType<typeof setTimeout> | undefined;
let abort: AbortController | null = null;

async function search(term: string): Promise<void> {
    const controller = new AbortController();
    abort = controller;

    try {
        const response = await fetch(`${route('admin.search')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (response.ok) {
            results.value = await response.json();
        }
    } catch {
        // Aborted by a newer keystroke, or offline: keep the last results.
    } finally {
        // A newer keystroke owns the spinner now.
        if (abort === controller) {
            loading.value = false;
        }
    }
}

watch(query, (value) => {
    activeIndex.value = 0;
    clearTimeout(timer);
    abort?.abort();
    abort = null;

    if (value.trim().length < 2) {
        results.value = null;
        loading.value = false;

        return;
    }

    // Counted as loading from the keystroke, so "no results" never flashes
    // during the debounce before the server has been asked.
    loading.value = true;
    timer = setTimeout(() => search(value.trim()), 200);
});

/* --- Keyboard ------------------------------------------------------------- */

function move(step: number): void {
    const count = flat.value.length;

    if (count === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + step + count) % count;

    nextTick(() => listRef.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}

function onKeydown(event: KeyboardEvent): void {
    if (event.isComposing) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        flat.value[activeIndex.value]?.select();
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            results.value = null;
            activeIndex.value = 0;
            // After the modal's own focus handling has run.
            setTimeout(() => inputRef.value?.focus(), 60);
        }
    },
);

/* Results from the old page are stale once the user has gone somewhere. */
watch(
    () => page.url,
    () => close(),
);

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const shortcut = isMac ? '⌘K' : 'Ctrl K';

defineExpose({ shortcut });
</script>

<template>
    <Modal
        :open="open"
        :footer="null"
        :closable="false"
        :width="640"
        :style="{ top: '72px' }"
        :body-style="{ padding: 0 }"
        wrap-class-name="admin-palette"
        :aria-label="t('admin.palette.label')"
        @cancel="close"
    >
        <div class="admin-palette-search">
            <Search class="admin-palette-search-icon" aria-hidden="true" />
            <Input
                ref="inputRef"
                v-model:value="query"
                :bordered="false"
                size="large"
                type="search"
                autocomplete="off"
                spellcheck="false"
                :placeholder="t('admin.palette.placeholder')"
                :aria-label="t('admin.palette.placeholder')"
                role="combobox"
                aria-controls="admin-palette-list"
                aria-expanded="true"
                :aria-activedescendant="flat[activeIndex] ? `palette-${flat[activeIndex].id}` : undefined"
                @keydown="onKeydown"
            />
            <Spin v-if="loading" size="small" />
            <kbd class="admin-kbd">Esc</kbd>
        </div>

        <div id="admin-palette-list" ref="listRef" class="admin-palette-list overlay-scroll" role="listbox">
            <p v-if="!canSearchRecords" class="admin-palette-note">{{ t('admin.palette.type_more') }}</p>

            <section v-for="section in sections" :key="section.key" class="admin-palette-section" role="group" :aria-label="section.title">
                <h3>{{ section.title }}</h3>
                <button
                    v-for="row in section.rows"
                    :id="`palette-${row.id}`"
                    :key="row.id"
                    type="button"
                    role="option"
                    class="admin-palette-row"
                    :data-active="indexOf(row) === activeIndex ? 'true' : 'false'"
                    :aria-selected="indexOf(row) === activeIndex"
                    tabindex="-1"
                    @mousemove="activeIndex = indexOf(row)"
                    @click="row.select()"
                >
                    <span class="admin-palette-row-icon" aria-hidden="true"><component :is="row.icon" /></span>
                    <span class="admin-palette-row-text">
                        <span class="admin-palette-row-label" :dir="row.userText ? 'auto' : undefined">{{ row.label }}</span>
                        <span v-if="row.sub" class="admin-palette-row-sub" :dir="row.userText ? 'auto' : undefined">{{ row.sub }}</span>
                    </span>
                    <span v-if="row.meta" class="admin-palette-row-meta">{{ row.meta }}</span>
                    <CornerDownLeft v-if="indexOf(row) === activeIndex" class="admin-palette-row-enter" aria-hidden="true" />
                </button>
            </section>

            <div v-if="noResults" class="admin-palette-empty">
                <FileText aria-hidden="true" />
                <p>{{ t('admin.palette.no_results', { q: query.trim() }) }}</p>
            </div>
        </div>

        <footer class="admin-palette-foot">
            <span><kbd class="admin-kbd">↑</kbd><kbd class="admin-kbd">↓</kbd> {{ t('admin.palette.hint_move') }}</span>
            <span><kbd class="admin-kbd">↵</kbd> {{ t('admin.palette.hint_open') }}</span>
            <span class="ms-auto"
                ><kbd class="admin-kbd">{{ shortcut }}</kbd> {{ t('admin.palette.hint_toggle') }}</span
            >
        </footer>
    </Modal>
</template>
