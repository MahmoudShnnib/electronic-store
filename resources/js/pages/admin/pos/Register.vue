<script setup lang="ts">
import ReceiptPaper, { type ReceiptOrder, type ReceiptStore } from '@/components/pos/ReceiptPaper.vue';
import { usePosPrinting } from '@/composables/usePosPrinting';
import PosLayout from '@/layouts/PosLayout.vue';
import { formatMoney } from '@/lib/money';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { Button, Empty, Input, InputNumber, message, Modal, Radio, Result, Tooltip, Typography } from 'ant-design-vue';
import { ChevronDown, ImageOff, Minus, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: PosLayout });

const RadioGroup = Radio.Group;
const RadioButton = Radio.Button;
const { Text } = Typography;
const { t } = useI18n();
const page = usePage();

interface VariantOption {
    id: number;
    name: string;
    sku: string;
    price: number;
    price_formatted: string;
    available: number;
    image: string | null;
}

interface ProductOption {
    id: number;
    name: string;
    name_ar: string | null;
    sku: string;
    barcode: string | null;
    has_variants: boolean;
    price: number;
    price_formatted: string;
    available: number;
    image: string | null;
    category_id: number | null;
    variants: VariantOption[];
}

interface CategoryChip {
    id: number;
    name: string;
    name_ar: string | null;
    products_count: number;
}

/** One sellable thing on the grid — a plain product, or one variant of one. */
interface Tile {
    key: string;
    product: ProductOption;
    variant: VariantOption | null;
    title: string;
    subtitle: string;
    priceFormatted: string;
    /** What the warehouse holds, as of the last poll. */
    stock: number;
    /** What can still be tapped: `stock` minus whatever the basket already holds. */
    available: number;
    image: string | null;
}

const props = defineProps<{
    shift: {
        id: number;
        cashier: string | null;
        warehouse: { id: number; name: string | null };
        orders_count: number;
        expected_cash_formatted: string;
    };
    products: ProductOption[];
    categories: CategoryChip[];
    paymentMethods: { value: string; label: string }[];
    recentSales: { id: number; number: string; total_formatted: string; created_at: string }[];
    // Flashed for one render straight after a sale, null otherwise.
    justSold: { order: ReceiptOrder; store: ReceiptStore; printer: { direct: boolean; width: number } } | null;
}>();

function money(amount: number): string {
    return formatMoney(amount, (page.props.locale as string) ?? 'en');
}

const locale = computed(() => (page.props.locale as string) ?? 'en');

function categoryLabel(category: CategoryChip): string {
    return locale.value === 'ar' && category.name_ar ? category.name_ar : category.name;
}

/** null is "everything" — the chip row's default. */
const activeCategory = ref<number | null>(null);

const canDiscount = computed(() => {
    const auth = page.props.auth as { user: { roles: string[]; permissions: string[] } | null };

    return auth.user?.roles.includes('super_admin') || (auth.user?.permissions.includes('pos.discount') ?? false);
});

/* -- Basket ---------------------------------------------------------------- */

interface BasketLine {
    key: string;
    product_id: number;
    product_variant_id: number | null;
    name: string;
    subtitle: string;
    unitPrice: number;
    quantity: number;
    image: string | null;
}

const basket = ref<BasketLine[]>([]);

/**
 * What the basket is already holding of each tile.
 *
 * The grid subtracts this rather than showing the raw warehouse figure, so a
 * shelf of two reads "2 left", then "1 left", then stops existing. The cashier
 * cannot tap their way past the stock, and cannot be looking at a tile for
 * something that is already all in the basket.
 */
const reserved = computed(() => {
    const held = new Map<string, number>();

    for (const line of basket.value) {
        held.set(line.key, line.quantity);
    }

    return held;
});

/** The warehouse figure behind a basket line, as of the last poll. */
function stockOf(line: BasketLine): number {
    return stockByKey.value.get(line.key) ?? 0;
}

/**
 * Adding the same item twice bumps the quantity rather than stacking a second
 * line — a cashier scanning three identical items expects "×3", not three rows.
 */
function addTile(tile: Tile): void {
    // `available` is already net of the basket, so this is the shelf refusing a
    // tap it cannot honour — not a second guard on the same number.
    if (tile.available < 1) return;

    const existing = basket.value.find((line) => line.key === tile.key);

    if (existing) {
        existing.quantity += 1;

        return;
    }

    basket.value.push({
        key: tile.key,
        product_id: tile.product.id,
        product_variant_id: tile.variant?.id ?? null,
        name: tile.title,
        subtitle: tile.subtitle,
        unitPrice: tile.variant ? tile.variant.price : tile.product.price,
        quantity: 1,
        image: tile.image,
    });
}

function step(line: BasketLine, by: number): void {
    const next = line.quantity + by;

    if (next < 1) {
        removeLine(line);

        return;
    }

    line.quantity = Math.min(next, stockOf(line));
}

function removeLine(line: BasketLine): void {
    basket.value = basket.value.filter((row) => row.key !== line.key);
}

function clearBasket(): void {
    basket.value = [];
    discount.value = 0;
    couponCode.value = '';
    customerName.value = '';
    customerPhone.value = '';
    showDetails.value = false;
}

/* -- Catalogue ------------------------------------------------------------- */

const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

/**
 * A product with variants is not sellable as itself, so each variant becomes
 * its own tile. Flattening here rather than in the template is what lets the
 * grid stay one uniform list — nested v-for produced rows of ragged height.
 */
const catalogue = computed<Omit<Tile, 'available'>[]>(() =>
    props.products.flatMap((product) =>
        product.has_variants
            ? product.variants.map((variant) => ({
                  key: `${product.id}:${variant.id}`,
                  product,
                  variant,
                  title: product.name,
                  subtitle: variant.name,
                  priceFormatted: variant.price_formatted,
                  stock: variant.available,
                  image: variant.image,
              }))
            : [
                  {
                      key: `${product.id}:0`,
                      product,
                      variant: null,
                      title: product.name,
                      subtitle: product.sku,
                      priceFormatted: product.price_formatted,
                      stock: product.available,
                      image: product.image,
                  },
              ],
    ),
);

/** Keyed the same way as the basket, which is how a line finds its stock back. */
const stockByKey = computed(() => new Map(catalogue.value.map((tile) => [tile.key, tile.stock])));

/**
 * The shelf: everything that can still be sold right now.
 *
 * Sold out — on the shelf or into this basket — means gone, not greyed out. A
 * dimmed tile is still a tile to read past, and the one thing a cashier must
 * never do is offer something the warehouse cannot hand over.
 */
const tiles = computed<Tile[]>(() =>
    catalogue.value.map((tile) => ({ ...tile, available: tile.stock - (reserved.value.get(tile.key) ?? 0) })).filter((tile) => tile.available > 0),
);

/**
 * Chips counted off the shelf rather than off the server's catalogue: the count
 * has to agree with what tapping the chip actually shows, and a category whose
 * last piece just went into the basket should not still be offered.
 */
const shelves = computed(() => {
    const counts = new Map<number, number>();

    for (const tile of tiles.value) {
        if (tile.product.category_id === null) continue;

        counts.set(tile.product.category_id, (counts.get(tile.product.category_id) ?? 0) + 1);
    }

    return props.categories
        .filter((category) => counts.has(category.id))
        .map((category) => ({ ...category, products_count: counts.get(category.id) ?? 0 }));
});

// A chip that empties out takes its filter with it, rather than leaving the
// cashier staring at an empty shelf with no obvious way back.
watch(shelves, (list) => {
    if (activeCategory.value !== null && !list.some((category) => category.id === activeCategory.value)) {
        activeCategory.value = null;
    }
});

/**
 * Searched in the browser over the whole catalogue: a cashier scanning a
 * barcode cannot wait for a round trip, and the scanner types the code and
 * presses Enter faster than a debounce would settle.
 */
const results = computed<Tile[]>(() => {
    const term = search.value.trim().toLowerCase();

    // The chip narrows the shelf; the search box then looks only inside it.
    const inCategory = activeCategory.value === null ? tiles.value : tiles.value.filter((tile) => tile.product.category_id === activeCategory.value);

    if (term === '') return inCategory.slice(0, 60);

    return inCategory
        .filter(
            (tile) =>
                tile.title.toLowerCase().includes(term) ||
                tile.subtitle.toLowerCase().includes(term) ||
                (tile.product.name_ar?.toLowerCase().includes(term) ?? false) ||
                tile.product.sku.toLowerCase().includes(term) ||
                (tile.variant?.sku.toLowerCase().includes(term) ?? false) ||
                (tile.product.barcode?.toLowerCase().includes(term) ?? false),
        )
        .slice(0, 60);
});

/**
 * A barcode scanner ends its input with Enter. An exact barcode or SKU match
 * goes straight into the basket and clears the box, ready for the next scan;
 * anything else leaves the results on screen to be tapped.
 */
function matchesCode(tile: { product: ProductOption; variant: VariantOption | null }, term: string): boolean {
    return (
        tile.product.barcode?.toLowerCase() === term ||
        tile.variant?.sku.toLowerCase() === term ||
        (tile.variant === null && tile.product.sku.toLowerCase() === term)
    );
}

function onSearchEnter(): void {
    const term = search.value.trim().toLowerCase();

    if (term === '') return;

    const exact = tiles.value.find((tile) => matchesCode(tile, term));

    const hit = exact ?? (results.value.length === 1 ? results.value[0] : null);

    if (hit) {
        // A scanned code outranks the chip: the item in the cashier's hand is
        // the item they meant, whatever shelf is on screen.
        addTile(hit);
        search.value = '';

        return;
    }

    // The shelf hides whatever has nothing left, so scanning the last piece —
    // already in the basket — now matches nothing at all. Say why: a scanner
    // that silently does nothing reads as a broken scanner.
    const soldOut = catalogue.value.find((tile) => matchesCode(tile, term));

    if (soldOut) {
        message.warning(t('admin.pos.sold_out', { name: soldOut.title }));
        search.value = '';
    }
}

/**
 * The till is driven from the keyboard: F2, scan, Enter, Enter.
 *
 * Enter is handled here rather than left to the focused button because AntD
 * teleports the dialog and traps focus itself, so which control ends up focused
 * is not ours to guarantee. Handling the key means the cashier never has to
 * reach for the mouse, whatever the dialog decided to focus.
 */
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'F2') {
        event.preventDefault();
        searchInput.value?.focus();

        return;
    }

    // `repeat` guards a key held down from the previous step: a sale must be a
    // deliberate press, not the tail of one.
    if (event.key === 'Enter' && confirming.value && !event.repeat) {
        event.preventDefault();
        checkout();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

/* -- Totals ---------------------------------------------------------------- */

const discount = ref(0);
const couponCode = ref('');
const customerName = ref('');
const customerPhone = ref('');
const paymentMethod = ref(props.paymentMethods[0]?.value ?? 'cash');

/**
 * Name, phone and coupon are wanted on a small minority of counter sales, so
 * they stay folded away — the default screen is the basket and the total, not
 * a form.
 */
const showDetails = ref(false);

const subtotal = computed(() => basket.value.reduce((sum, line) => sum + line.unitPrice * line.quantity, 0));

const itemCount = computed(() => basket.value.reduce((sum, line) => sum + line.quantity, 0));

/**
 * The coupon is not priced here — the server owns that. This preview only
 * subtracts the keyed-in discount, so it never claims a total the server
 * would disagree with in the other direction.
 */
const total = computed(() => Math.max(0, subtotal.value - (discount.value ?? 0)));

/* -- Checkout -------------------------------------------------------------- */

const submitting = ref(false);
const confirming = ref(false);

/** The label of the tender the cashier picked, for the confirmation dialog. */
const paymentLabel = computed(() => props.paymentMethods.find((m) => m.value === paymentMethod.value)?.label ?? paymentMethod.value);

const basketCount = computed(() => basket.value.reduce((sum, line) => sum + line.quantity, 0));

/**
 * Taking payment asks first.
 *
 * A counter sale commits stock, banks the money and cuts paper in one press, and
 * none of that is undoable from the register — a mis-tapped tile or a stale
 * basket becomes a refund. One dialog showing the amount about to be charged is
 * cheap next to that.
 */
function askToConfirm(): void {
    if (basket.value.length === 0 || submitting.value) return;

    confirming.value = true;
}

const confirmButton = ref<{ $el?: HTMLElement } | null>(null);

/**
 * Highlight the confirm button when the dialog opens.
 *
 * Two frames, not `nextTick`: the modal is teleported and animated in, and AntD
 * moves focus into it as part of that. Focusing any earlier is simply undone.
 * Enter works without this — see `onKeydown` — but the cashier should be able to
 * see what pressing it will do.
 */
watch(confirming, (open) => {
    if (!open) return;

    requestAnimationFrame(() => requestAnimationFrame(() => confirmButton.value?.$el?.focus()));
});

function checkout(): void {
    if (basket.value.length === 0 || submitting.value) return;

    confirming.value = false;
    submitting.value = true;

    router.post(
        route('admin.pos.sales.store'),
        {
            items: basket.value.map((line) => ({
                product_id: line.product_id,
                product_variant_id: line.product_variant_id,
                quantity: line.quantity,
            })),
            payment_method: paymentMethod.value,
            coupon_code: couponCode.value || null,
            discount: canDiscount.value ? discount.value : 0,
            customer: { name: customerName.value || null, phone: customerPhone.value || null },
        },
        {
            // Leaving the basket populated on a failed sale is deliberate, so
            // nothing has to be re-scanned.
            onSuccess: () => clearBasket(),
            onFinish: () => (submitting.value = false),
        },
    );
}

/* -- The sale just rung up -------------------------------------------------- */

/**
 * After a sale the server sends the slip back here rather than routing the
 * cashier to a receipt page. It is rendered off-screen, photographed, and sent
 * to the printer; the cashier sees a dialog over the basket they are already
 * looking at and never changes screen.
 */
const paper = ref<InstanceType<typeof ReceiptPaper> | null>(null);
const sold = ref<{ order: ReceiptOrder; store: ReceiptStore } | null>(null);
const printed = ref<boolean | null>(null);
const { sending, printSlip } = usePosPrinting(() => props.justSold?.printer.width ?? 576);

async function printSold(): Promise<void> {
    if (sold.value === null) return;

    printed.value = null;
    // The paper has to exist in the DOM before it can be photographed.
    await nextTick();
    printed.value = await printSlip(paper.value?.el ?? null, sold.value.order.id);
}

watch(
    () => props.justSold,
    async (sale) => {
        if (!sale) return;

        sold.value = { order: sale.order, store: sale.store };

        if (!sale.printer.direct) {
            // No counter printer configured: the dialog offers the receipt page,
            // where the browser's own print dialog still produces a slip.
            printed.value = false;

            return;
        }

        await printSold();
    },
    { immediate: true },
);

function closeSold(): void {
    sold.value = null;
    printed.value = null;
    // Straight back to scanning, which is the only thing the cashier does next.
    searchInput.value?.focus();
}

/* -- Live stock ------------------------------------------------------------- */

/**
 * The register holds its whole catalogue in the page, so a sale rung up on the
 * other till — or a delivery booked in the back office — would otherwise stay
 * invisible until someone reloaded. The shelf re-reads itself instead.
 *
 * A partial reload keeps the basket, the search box and the scroll position,
 * and `usePoll` backs itself off while the tab is in the background, so an idle
 * till does not sit there asking.
 */
const stockPoll = usePoll(
    10_000,
    {
        only: ['products', 'categories', 'shift', 'recentSales'],
        showProgress: false,
        // The stop() below is the ordinary guard; this is the one that holds when
        // the poll restarts itself — it does that on its own when the tab comes
        // back to the front, and it does not know a sale is in flight.
        onBefore: () => !midSale.value,
    },
    { autoStart: false },
);

/**
 * Nothing may reload mid-sale. An Inertia visit started while the checkout POST
 * is in flight cancels it, and a shelf that shifts under an open confirmation
 * changes what the cashier is agreeing to.
 */
const midSale = computed(() => confirming.value || submitting.value || sold.value !== null);

watch(midSale, (busy) => (busy ? stockPoll.stop() : stockPoll.start()), { immediate: true });

/**
 * A poll can land on a basket that is now bigger than the warehouse.
 *
 * The other till sold the last one while this basket was still holding it, and
 * the server would refuse the sale at payment. Trim the line to what is really
 * left and say so — the alternative is a confirmation dialog quoting a total
 * that cannot be charged.
 */
watch(
    () => props.products,
    () => {
        for (const line of [...basket.value]) {
            const stock = stockOf(line);

            if (stock < 1) {
                removeLine(line);
                message.warning(t('admin.pos.line_gone', { name: line.name }));

                continue;
            }

            if (line.quantity > stock) {
                line.quantity = stock;
                message.warning(t('admin.pos.line_trimmed', { name: line.name, count: stock }));
            }
        }
    },
);
</script>

<template>
    <Head :title="t('admin.pos.title')" />

    <div class="flex h-full flex-col bg-ad-bg">
        <!-- The shift strip lives in the page, not the layout: an Inertia page
             renders into the layout's default slot, so a named slot here would
             never reach it. -->
        <div class="flex shrink-0 flex-wrap items-center gap-x-5 gap-y-1 border-b border-ad-line bg-ad-card px-4 py-2">
            <span class="flex items-center gap-1.5 text-sm font-semibold">
                <span class="size-1.5 rounded-full bg-ad-success"></span>
                {{ shift.warehouse.name }}
            </span>

            <!-- Each figure carries its own label: the run-on of dot-separated
                 values it replaced was unreadable at a glance, which is the only
                 way a cashier ever reads this strip. -->
            <span class="text-xs text-ad-muted">
                {{ t('admin.pos.cashier') }}
                <span class="font-medium text-ad-text">{{ shift.cashier }}</span>
            </span>
            <span class="text-xs text-ad-muted">
                {{ t('admin.pos.sales_count') }}
                <span class="font-medium tabular-nums text-ad-text">{{ shift.orders_count }}</span>
            </span>
            <span class="text-xs text-ad-muted">
                {{ t('admin.pos.expected_in_drawer') }}
                <span class="font-medium tabular-nums text-ad-text">{{ shift.expected_cash_formatted }}</span>
            </span>

            <Link :href="route('admin.pos.shifts.close')" class="ms-auto">
                <Button size="small">{{ t('admin.pos.close_shift') }}</Button>
            </Link>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[1fr_25rem]">
            <!-- Catalogue -->
            <section class="flex min-h-0 flex-col border-e border-ad-line">
                <!-- One scrolling row, not a wrapping block: the shelf must never
                     push the search box and the grid down the screen. -->
                <div v-if="shelves.length > 0" class="flex shrink-0 gap-1.5 overflow-x-auto border-b border-ad-line px-3 py-2">
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition"
                        :class="
                            activeCategory === null
                                ? 'border-ad-primary bg-ad-primary text-white'
                                : 'border-ad-line text-ad-text-3 hover:border-ad-accent'
                        "
                        @click="activeCategory = null"
                    >
                        {{ t('admin.pos.all_categories') }}
                    </button>

                    <button
                        v-for="category in shelves"
                        :key="category.id"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition"
                        :class="
                            activeCategory === category.id
                                ? 'border-ad-primary bg-ad-primary text-white'
                                : 'border-ad-line text-ad-text-3 hover:border-ad-accent'
                        "
                        @click="activeCategory = activeCategory === category.id ? null : category.id"
                    >
                        {{ categoryLabel(category) }}
                        <span class="ms-1 tabular-nums opacity-60">{{ category.products_count }}</span>
                    </button>
                </div>

                <div class="shrink-0 p-3">
                    <Input
                        ref="searchInput"
                        v-model:value="search"
                        size="large"
                        allow-clear
                        :placeholder="t('admin.pos.scan_placeholder')"
                        @press-enter="onSearchEnter"
                    >
                        <template #prefix><Search class="size-4 text-ad-label" /></template>
                    </Input>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                    <Empty v-if="results.length === 0" :description="t('admin.pos.no_products')" class="!mt-12" />

                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                        <button
                            v-for="tile in results"
                            :key="tile.key"
                            type="button"
                            class="group flex h-full flex-col rounded-lg border border-ad-line bg-ad-card p-2.5 text-start transition hover:border-ad-accent"
                            @click="addTile(tile)"
                        >
                            <!-- Square, so every tile in a row is the same height
                                 whatever aspect ratio was uploaded. object-contain
                                 rather than cover: a cropped product is harder to
                                 recognise than one with air around it. -->
                            <span class="mb-2 flex aspect-square w-full items-center justify-center overflow-hidden rounded bg-ad-icon-bg">
                                <img
                                    v-if="tile.image"
                                    :src="tile.image"
                                    :alt="tile.title"
                                    loading="lazy"
                                    class="size-full object-contain transition-transform duration-200 group-hover:scale-105"
                                />
                                <ImageOff v-else class="size-6 text-ad-faint" />
                            </span>

                            <!-- dir="auto" so a Latin product name truncates at its
                                 own end. Left as RTL it clipped the beginning, which
                                 is the half that identifies the product. -->
                            <span dir="auto" class="line-clamp-2 min-h-[2.5rem] text-[0.8125rem] font-medium leading-tight">
                                {{ tile.title }}
                            </span>

                            <span dir="auto" class="mt-1 line-clamp-1 self-start rounded bg-ad-icon-bg px-1.5 py-0.5 text-[0.6875rem] text-ad-muted">
                                {{ tile.subtitle }}
                            </span>

                            <!-- Pinned to the bottom so every tile in a row lines its
                                 price up, whatever the title wrapped to. -->
                            <span class="mt-auto flex items-baseline justify-between gap-1 pt-2">
                                <span class="text-sm font-semibold text-ad-accent">{{ tile.priceFormatted }}</span>
                                <span
                                    class="shrink-0 text-[0.6875rem] font-medium tabular-nums"
                                    :class="tile.available > 3 ? 'text-ad-label' : 'is-warning'"
                                >
                                    {{ t('admin.pos.in_stock_n', { count: tile.available }) }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Basket -->
            <aside class="flex min-h-0 flex-col bg-ad-card">
                <div class="flex shrink-0 items-center justify-between border-b border-ad-line px-3 py-2">
                    <span class="text-sm font-semibold">{{ t('admin.pos.basket_n', { count: itemCount }) }}</span>
                    <Tooltip :title="t('admin.pos.clear_basket')">
                        <Button type="text" size="small" :disabled="basket.length === 0" @click="clearBasket">
                            <Trash2 class="size-4" />
                        </Button>
                    </Tooltip>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <Empty v-if="basket.length === 0" :description="t('admin.pos.basket_empty')" class="!mt-16" />

                    <ul v-else class="divide-y divide-ad-line">
                        <li v-for="line in basket" :key="line.key" class="px-3 py-2.5">
                            <div class="flex items-start gap-2">
                                <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded bg-ad-icon-bg">
                                    <img v-if="line.image" :src="line.image" :alt="line.name" loading="lazy" class="size-full object-contain" />
                                    <ImageOff v-else class="size-3.5 text-ad-faint" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p dir="auto" class="line-clamp-2 text-[0.8125rem] font-medium leading-tight">{{ line.name }}</p>
                                    <p class="mt-0.5 text-[0.6875rem] text-ad-muted">{{ line.quantity }} × {{ money(line.unitPrice) }}</p>
                                </div>

                                <span class="shrink-0 text-sm font-semibold tabular-nums">
                                    {{ money(line.unitPrice * line.quantity) }}
                                </span>

                                <button
                                    type="button"
                                    class="-me-1 shrink-0 rounded p-1 text-ad-label transition hover:bg-ad-hover hover:text-ad-danger"
                                    :aria-label="t('admin.common.delete')"
                                    @click="removeLine(line)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>

                            <!-- Full-width stepper: the 24px buttons this replaced
                                 were the hardest thing on the screen to hit. -->
                            <div class="mt-2 flex items-center gap-1">
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded border border-ad-line transition hover:border-ad-accent"
                                    :aria-label="t('admin.pos.decrease')"
                                    @click="step(line, -1)"
                                >
                                    <Minus class="size-3.5" />
                                </button>
                                <span class="w-9 text-center text-sm font-medium tabular-nums">{{ line.quantity }}</span>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded border border-ad-line transition hover:border-ad-accent disabled:opacity-30 disabled:hover:border-ad-line"
                                    :disabled="line.quantity >= stockOf(line)"
                                    :aria-label="t('admin.pos.increase')"
                                    @click="step(line, 1)"
                                >
                                    <Plus class="size-3.5" />
                                </button>
                                <Text v-if="line.quantity >= stockOf(line)" type="warning" class="!ms-1 !text-[0.6875rem]">
                                    {{ t('admin.pos.max_stock') }}
                                </Text>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="shrink-0 border-t border-ad-line">
                    <!-- Optional fields, folded: most counter sales need none of them. -->
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-xs text-ad-muted transition hover:text-ad-text"
                        @click="showDetails = !showDetails"
                    >
                        {{ t('admin.pos.more_details') }}
                        <ChevronDown class="size-3.5 transition-transform" :class="showDetails && 'rotate-180'" />
                    </button>

                    <div v-if="showDetails" class="space-y-2 px-3 pb-2">
                        <div class="grid grid-cols-2 gap-2">
                            <Input v-model:value="customerName" size="small" :placeholder="t('admin.pos.customer_name')" />
                            <Input v-model:value="customerPhone" size="small" :placeholder="t('admin.pos.customer_phone')" />
                        </div>
                        <Input v-model:value="couponCode" size="small" :placeholder="t('admin.pos.coupon_code')" />
                    </div>

                    <div class="space-y-2 px-3 pb-3">
                        <div v-if="canDiscount" class="flex items-center gap-2">
                            <span class="w-16 shrink-0 text-xs text-ad-muted">{{ t('admin.pos.discount') }}</span>
                            <InputNumber v-model:value="discount" :min="0" :max="subtotal" size="small" class="!flex-1" />
                        </div>

                        <RadioGroup v-model:value="paymentMethod" button-style="solid" class="!flex">
                            <RadioButton v-for="method in paymentMethods" :key="method.value" :value="method.value" class="!flex-1 !text-center">
                                {{ method.label }}
                            </RadioButton>
                        </RadioGroup>

                        <!-- A summary, not a bare total: a customer querying the
                             price needs to see what was taken off. -->
                        <dl class="space-y-1 border-t border-ad-line pt-2 text-xs">
                            <div class="flex justify-between text-ad-muted">
                                <dt>{{ t('admin.pos.subtotal') }}</dt>
                                <dd class="tabular-nums">{{ money(subtotal) }}</dd>
                            </div>
                            <div v-if="discount > 0" class="is-warning flex justify-between">
                                <dt>{{ t('admin.pos.discount') }}</dt>
                                <dd class="tabular-nums">−{{ money(discount) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between pt-0.5">
                                <dt class="text-sm font-medium">{{ t('admin.pos.total') }}</dt>
                                <dd class="text-2xl font-bold tabular-nums">{{ money(total) }}</dd>
                            </div>
                        </dl>

                        <Button type="primary" size="large" block :disabled="basket.length === 0" :loading="submitting" @click="askToConfirm">
                            {{ t('admin.pos.take_payment') }}
                        </Button>

                        <div v-if="recentSales.length > 0" class="flex flex-wrap gap-1 pt-0.5">
                            <Link
                                v-for="sale in recentSales"
                                :key="sale.id"
                                :href="route('admin.pos.receipt', sale.id)"
                                class="rounded border border-ad-line px-1.5 py-0.5 text-[0.6875rem] text-ad-muted transition hover:border-ad-accent hover:text-ad-accent"
                            >
                                {{ sale.number.slice(-6) }}
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Asked before anything is committed. The confirm button is focused on
         open and Enter is bound in `onKeydown`, so the whole sale is keyboard
         work: F2, scan, Enter, Enter — no reaching for the mouse. -->
    <Modal :open="confirming" :title="t('admin.pos.confirm_sale')" centered width="360px" @cancel="confirming = false">
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button @click="confirming = false">{{ t('admin.common.cancel') }}</Button>
                <Button ref="confirmButton" type="primary" size="large" @click="checkout">
                    {{ t('admin.pos.confirm_sale') }}
                </Button>
            </div>
        </template>

        <div class="space-y-2 py-2">
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-sm text-ad-muted">{{ t('admin.pos.total') }}</span>
                <span class="text-3xl font-bold text-ad-text">{{ money(total) }}</span>
            </div>
            <div class="flex justify-between gap-2 text-sm">
                <span class="text-ad-muted">{{ t('admin.pos.payment') }}</span>
                <span dir="auto">{{ paymentLabel }}</span>
            </div>
            <div class="flex justify-between gap-2 text-sm">
                <span class="text-ad-muted">{{ t('admin.pos.items_count') }}</span>
                <span>{{ basketCount }}</span>
            </div>
            <div v-if="canDiscount && (discount ?? 0) > 0" class="flex justify-between gap-2 text-sm">
                <span class="text-ad-muted">{{ t('admin.pos.discount') }}</span>
                <span>-{{ money(discount ?? 0) }}</span>
            </div>
            <div v-if="customerName" class="flex justify-between gap-2 text-sm">
                <span class="text-ad-muted">{{ t('admin.pos.customer') }}</span>
                <span dir="auto">{{ customerName }}</span>
            </div>
        </div>
    </Modal>

    <!-- The sale just rung up.
         The paper is parked off-screen rather than hidden: html2canvas measures
         real layout, and `display:none` has none to measure. -->
    <template v-if="sold">
        <div class="receipt-offscreen pointer-events-none fixed -left-[10000px] top-0" aria-hidden="true">
            <ReceiptPaper ref="paper" :order="sold.order" :store="sold.store" />
        </div>

        <Modal :open="true" :footer="null" :closable="false" :mask-closable="false" centered width="380px" @cancel="closeSold">
            <Result :status="printed === false ? 'warning' : 'success'">
                <template #title>
                    <span class="text-base">{{ t('admin.pos.sale_complete') }}</span>
                </template>
                <template #subTitle>
                    <div class="space-y-1 text-center">
                        <p class="text-2xl font-bold text-ad-text">{{ sold.order.total_formatted }}</p>
                        <p class="text-xs">{{ sold.order.number }}</p>
                        <p v-if="sending" class="text-xs">{{ t('admin.pos.printing') }}</p>
                        <p v-else-if="printed" class="is-money text-xs">{{ t('admin.pos.printed') }}</p>
                        <p v-else-if="printed === false" class="is-warning text-xs">{{ t('admin.pos.print_failed_short') }}</p>
                    </div>
                </template>

                <template #extra>
                    <div class="flex flex-col gap-2">
                        <Button type="primary" size="large" block @click="closeSold">{{ t('admin.pos.next_sale') }}</Button>
                        <div class="flex gap-2">
                            <!-- With no counter printer there is nothing to send
                                 to, so paper has to come from the receipt page
                                 and its browser dialog: `print=1` opens it. -->
                            <Button v-if="justSold?.printer.direct" :loading="sending" block @click="printSold">
                                {{ t('admin.pos.print_again') }}
                            </Button>
                            <Link v-else :href="route('admin.pos.receipt', { order: sold.order.id, print: 1 })" class="flex-1">
                                <Button block>{{ t('admin.pos.print_again') }}</Button>
                            </Link>

                            <!-- Plain link, no `print`: this one is for looking. -->
                            <Link :href="route('admin.pos.receipt', sold.order.id)" class="flex-1">
                                <Button block>{{ t('admin.pos.open_receipt') }}</Button>
                            </Link>
                        </div>
                    </div>
                </template>
            </Result>
        </Modal>
    </template>
</template>
