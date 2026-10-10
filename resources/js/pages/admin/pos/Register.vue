<script setup lang="ts">
import ReceiptPaper, { type ReceiptOrder, type ReceiptStore } from '@/components/pos/ReceiptPaper.vue';
import { usePosPrinting } from '@/composables/usePosPrinting';
import PosLayout from '@/layouts/PosLayout.vue';
import { formatMoney } from '@/lib/money';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { Button, Empty, Input, InputNumber, message, Modal, Radio, Result, Select, Tooltip, Typography } from 'ant-design-vue';
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
    /** Admins only: the highest price paid for the units in this warehouse. */
    max_cost_formatted?: string;
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
    max_cost_formatted?: string;
    image: string | null;
    category_id: number | null;
    brand_id: number | null;
    brand: Chip | null;
    variants: VariantOption[];
}

/** A category or a brand, as the filter row shows it. */
interface Chip {
    id: number;
    name: string;
    name_ar: string | null;
}

interface CountedChip extends Chip {
    products_count: number;
}

/**
 * One sellable thing — a plain product, or one variant of one. Keyed the way the
 * basket keys its lines, which is how a line finds its stock back.
 */
interface Unit {
    key: string;
    product: ProductOption;
    variant: VariantOption | null;
    title: string;
    subtitle: string;
    price: number;
    priceFormatted: string;
    /** What the warehouse holds, as of the last poll. */
    stock: number;
    /** What can still be tapped: `stock` minus whatever the basket already holds. */
    available: number;
    /** Admins only (absent for cashiers): the highest price paid for the units held. */
    maxCost?: string;
    image: string | null;
}

/**
 * One card on the grid — always a whole product.
 *
 * A product with variants is one card that opens a picker, not a row of
 * near-identical cards differing only in a word of small print. The cashier
 * looks for the phone, then picks the colour; they do not scan four "iPhone"
 * tiles to find the one that says 256GB.
 */
interface Tile {
    key: string;
    product: ProductOption;
    title: string;
    subtitle: string;
    priceFormatted: string;
    image: string | null;
    /** One for a plain product, one per active variant otherwise. */
    units: Unit[];
    available: number;
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
    categories: CountedChip[];
    brands: CountedChip[];
    paymentMethods: { value: string; label: string }[];
    recentSales: { id: number; number: string; total_formatted: string; created_at: string }[];
    // Flashed for one render straight after a sale, null otherwise.
    justSold: { order: ReceiptOrder; store: ReceiptStore; printer: { direct: boolean; width: number } } | null;
}>();

function money(amount: number): string {
    return formatMoney(amount, (page.props.locale as string) ?? 'en');
}

const locale = computed(() => (page.props.locale as string) ?? 'en');

function chipLabel(chip: Chip): string {
    return locale.value === 'ar' && chip.name_ar ? chip.name_ar : chip.name;
}

/** null is "everything" — the chip row's default. */
const activeCategory = ref<number | null>(null);

/**
 * The second and third filters, beside the search box rather than in a chip row
 * of their own: three stacked rows of chips would push the grid off the screen,
 * which is the one thing the shelf cannot afford.
 */
const activeBrand = ref<number | undefined>(undefined);
const stockMode = ref<'all' | 'in' | 'out'>('all');

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
 * Tapping a card. A plain product drops straight into the basket; one with
 * variants asks which, because guessing on the cashier's behalf is how the
 * wrong colour ends up on the receipt.
 */
function addTile(tile: Tile): void {
    if (tile.available < 1) return;

    if (tile.units.length === 1) {
        addUnit(tile.units[0]);

        return;
    }

    pickingId.value = tile.product.id;
}

/**
 * Adding the same item twice bumps the quantity rather than stacking a second
 * line — a cashier scanning three identical items expects "×3", not three rows.
 */
function addUnit(unit: Unit): void {
    // `available` is already net of the basket, so this is the shelf refusing a
    // tap it cannot honour — not a second guard on the same number.
    if (unit.available < 1) return;

    const existing = basket.value.find((line) => line.key === unit.key);

    if (existing) {
        existing.quantity += 1;

        return;
    }

    basket.value.push({
        key: unit.key,
        product_id: unit.product.id,
        product_variant_id: unit.variant?.id ?? null,
        // The variant is part of the name here, the way the receipt prints it:
        // once the choice is made on the shelf, the basket is the only place it
        // can still be checked before the money is taken.
        name: unit.variant ? `${unit.title} — ${unit.variant.name}` : unit.title,
        subtitle: unit.subtitle,
        unitPrice: unit.price,
        quantity: 1,
        image: unit.image,
    });
}

/** Picked from the variant sheet: take it, then get out of the way. */
function pickUnit(unit: Unit): void {
    addUnit(unit);
    pickingId.value = null;
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
 * Every sellable thing, flat: a plain product contributes one entry, a product
 * with variants contributes one per variant. The grid does not render this —
 * it is what the basket, the scanner and the stock poll look things up in.
 */
const units = computed<Omit<Unit, 'available'>[]>(() =>
    props.products.flatMap((product): Omit<Unit, 'available'>[] =>
        product.has_variants
            ? product.variants.map((variant) => ({
                  key: `${product.id}:${variant.id}`,
                  product,
                  variant,
                  title: product.name,
                  subtitle: variant.name,
                  price: variant.price,
                  priceFormatted: variant.price_formatted,
                  stock: variant.available,
                  maxCost: variant.max_cost_formatted,
                  image: variant.image,
              }))
            : [
                  {
                      key: `${product.id}:0`,
                      product,
                      variant: null,
                      title: product.name,
                      subtitle: product.sku,
                      price: product.price,
                      priceFormatted: product.price_formatted,
                      stock: product.available,
                      maxCost: product.max_cost_formatted,
                      image: product.image,
                  },
              ],
    ),
);

/** Keyed the same way as the basket, which is how a line finds its stock back. */
const stockByKey = computed(() => new Map(units.value.map((unit) => [unit.key, unit.stock])));

/** What the basket is already holding, subtracted. */
function withAvailability(unit: Omit<Unit, 'available'>): Unit {
    return { ...unit, available: unit.stock - (reserved.value.get(unit.key) ?? 0) };
}

/** Grouped once rather than filtered per product: the shelf is rebuilt on every poll. */
const unitsByProduct = computed(() => {
    const grouped = new Map<number, Unit[]>();

    for (const unit of units.value) {
        const own = grouped.get(unit.product.id);

        if (own) {
            own.push(withAvailability(unit));
        } else {
            grouped.set(unit.product.id, [withAvailability(unit)]);
        }
    }

    return grouped;
});

/**
 * The shelf: one card per product, sold-out ones included.
 *
 * Nothing is hidden for being empty. A cashier asked for something the shelf has
 * none of needs to see that the shop stocks it at all — a tile that vanishes
 * reads as "we have never sold that", which is a different answer to give a
 * customer. The card is greyed and cannot be tapped, so the till still refuses
 * to promise what the warehouse cannot hand over.
 */
const tiles = computed<Tile[]>(() =>
    props.products.flatMap((product) => {
        const own = unitsByProduct.value.get(product.id) ?? [];

        // Flagged as having variants but with none active left: nothing to sell
        // and no price to put on the card.
        if (own.length === 0) return [];

        const cheapest = own.reduce((low, unit) => (unit.price < low.price ? unit : low), own[0]);
        // Variants rarely share a price and a card can only carry one, so an
        // uneven set is labelled as starting at the cheapest.
        const uniform = own.every((unit) => unit.price === cheapest.price);

        return [
            {
                key: String(product.id),
                product,
                title: product.name,
                subtitle: product.has_variants ? t('admin.pos.variants_n', { count: own.length }) : product.sku,
                priceFormatted: uniform ? cheapest.priceFormatted : t('admin.pos.price_from', { price: cheapest.priceFormatted }),
                image: product.image ?? cheapest.image,
                units: own,
                available: own.reduce((sum, unit) => sum + Math.max(0, unit.available), 0),
            },
        ];
    }),
);

/**
 * Chips counted off the shelf rather than off the server's catalogue: the count
 * has to agree with what tapping the chip actually shows.
 */
const shelves = computed(() => countChips(props.categories, (tile) => tile.product.category_id));

const brandShelves = computed(() => countChips(props.brands, (tile) => tile.product.brand_id));

function countChips(chips: CountedChip[], idOf: (tile: Tile) => number | null): CountedChip[] {
    const counts = new Map<number, number>();

    for (const tile of tiles.value) {
        const id = idOf(tile);

        if (id === null) continue;

        counts.set(id, (counts.get(id) ?? 0) + 1);
    }

    return chips.filter((chip) => counts.has(chip.id)).map((chip) => ({ ...chip, products_count: counts.get(chip.id) ?? 0 }));
}

const brandOptions = computed(() => brandShelves.value.map((brand) => ({ value: brand.id, label: `${chipLabel(brand)} (${brand.products_count})` })));

const stockOptions = computed(() => [
    { value: 'all', label: t('admin.pos.stock_any') },
    { value: 'in', label: t('admin.pos.stock_in') },
    { value: 'out', label: t('admin.pos.stock_out') },
]);

// A chip that empties out takes its filter with it, rather than leaving the
// cashier staring at an empty shelf with no obvious way back.
watch(shelves, (list) => {
    if (activeCategory.value !== null && !list.some((category) => category.id === activeCategory.value)) {
        activeCategory.value = null;
    }
});

watch(brandShelves, (list) => {
    if (activeBrand.value !== undefined && !list.some((brand) => brand.id === activeBrand.value)) {
        activeBrand.value = undefined;
    }
});

/**
 * Everything a cashier might have in their hand or in their head: the name in
 * either language, the brand, the product's own codes, and every variant's.
 */
function matches(tile: Tile, term: string): boolean {
    const product = tile.product;

    return (
        product.name.toLowerCase().includes(term) ||
        (product.name_ar?.toLowerCase().includes(term) ?? false) ||
        product.sku.toLowerCase().includes(term) ||
        (product.barcode?.toLowerCase().includes(term) ?? false) ||
        (product.brand?.name.toLowerCase().includes(term) ?? false) ||
        (product.brand?.name_ar?.toLowerCase().includes(term) ?? false) ||
        tile.units.some((unit) => unit.subtitle.toLowerCase().includes(term) || (unit.variant?.sku.toLowerCase().includes(term) ?? false))
    );
}

/**
 * Searched in the browser over the whole catalogue: a cashier scanning a
 * barcode cannot wait for a round trip, and the scanner types the code and
 * presses Enter faster than a debounce would settle.
 */
const results = computed<Tile[]>(() => {
    const term = search.value.trim().toLowerCase();

    return tiles.value
        .filter((tile) => activeCategory.value === null || tile.product.category_id === activeCategory.value)
        .filter((tile) => activeBrand.value === undefined || tile.product.brand_id === activeBrand.value)
        .filter((tile) => (stockMode.value === 'all' ? true : stockMode.value === 'in' ? tile.available > 0 : tile.available < 1))
        .filter((tile) => term === '' || matches(tile, term))
        .slice(0, 60);
});

/** The product whose variant sheet is open, kept live so the poll can empty it under the cashier. */
const pickingId = ref<number | null>(null);

const picking = computed<Tile | null>(() =>
    pickingId.value === null ? null : (tiles.value.find((tile) => tile.product.id === pickingId.value) ?? null),
);

/**
 * A barcode scanner ends its input with Enter. An exact barcode or SKU match
 * goes straight into the basket and clears the box, ready for the next scan;
 * anything else leaves the results on screen to be tapped.
 */
function matchesCode(unit: { product: ProductOption; variant: VariantOption | null }, term: string): boolean {
    return (
        unit.product.barcode?.toLowerCase() === term ||
        unit.variant?.sku.toLowerCase() === term ||
        (unit.variant === null && unit.product.sku.toLowerCase() === term)
    );
}

function onSearchEnter(): void {
    const term = search.value.trim().toLowerCase();

    if (term === '') return;

    // Matched against every unit, sold out ones included: a scanner that
    // silently does nothing on the last piece reads as a broken scanner.
    const scanned = units.value.find((unit) => matchesCode(unit, term));

    if (scanned) {
        const unit = withAvailability(scanned);

        if (unit.available < 1) {
            message.warning(t('admin.pos.sold_out', { name: unit.title }));
        } else {
            // A scanned code outranks the filters: the item in the cashier's
            // hand is the item they meant, whatever shelf is on screen.
            addUnit(unit);
        }

        search.value = '';

        return;
    }

    // No code matched, so this was typed rather than scanned. One card left on
    // screen with nothing to choose between goes in; anything else waits to be
    // tapped, because a variant sheet is a question, not a result.
    const only = results.value.length === 1 ? results.value[0] : null;

    if (only && only.units.length === 1 && only.available > 0) {
        addUnit(only.units[0]);
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
        only: ['products', 'categories', 'brands', 'shift', 'recentSales'],
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
            <section class="flex min-h-0 min-w-0 flex-col border-e border-ad-line">
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
                        {{ chipLabel(category) }}
                        <span class="ms-1 tabular-nums opacity-60">{{ category.products_count }}</span>
                    </button>
                </div>

                <div class="flex shrink-0 items-center gap-2 p-3">
                    <Input
                        ref="searchInput"
                        v-model:value="search"
                        size="large"
                        allow-clear
                        class="min-w-0 flex-1"
                        :placeholder="t('admin.pos.scan_placeholder')"
                        @press-enter="onSearchEnter"
                    >
                        <template #prefix><Search class="size-4 text-ad-label" /></template>
                    </Input>

                    <Select
                        v-if="brandShelves.length > 0"
                        v-model:value="activeBrand"
                        size="large"
                        allow-clear
                        class="w-40 shrink-0"
                        :options="brandOptions"
                        :placeholder="t('admin.pos.all_brands')"
                        :aria-label="t('admin.pos.all_brands')"
                    />

                    <Select
                        v-model:value="stockMode"
                        size="large"
                        class="w-36 shrink-0"
                        :options="stockOptions"
                        :aria-label="t('admin.pos.stock_any')"
                    />
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                    <Empty v-if="results.length === 0" :description="t('admin.pos.no_products')" class="!mt-12" />

                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                        <button
                            v-for="tile in results"
                            :key="tile.key"
                            type="button"
                            :disabled="tile.available < 1"
                            class="group flex h-full flex-col rounded-lg border border-ad-line bg-ad-card p-2.5 text-start transition"
                            :class="tile.available < 1 ? 'cursor-not-allowed opacity-60 grayscale' : 'hover:border-ad-accent'"
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
                                    class="size-full object-contain transition-transform duration-200"
                                    :class="tile.available > 0 && 'group-hover:scale-105'"
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

                                <!-- Sold out is a label, not an absence: the card
                                     stays on the shelf so the cashier can tell a
                                     customer "we stock it, we are out" rather than
                                     "we have never heard of it". -->
                                <span
                                    v-if="tile.available < 1"
                                    class="shrink-0 rounded bg-ad-hover px-1.5 py-0.5 text-[0.6875rem] font-medium text-ad-danger"
                                >
                                    {{ t('admin.pos.out_of_stock') }}
                                </span>
                                <span
                                    v-else
                                    class="shrink-0 text-[0.6875rem] font-medium tabular-nums"
                                    :class="tile.available > 3 ? 'text-ad-label' : 'is-warning'"
                                >
                                    {{ t('admin.pos.in_stock_n', { count: tile.available }) }}
                                </span>
                            </span>
                            <span v-if="tile.maxCost" class="text-[0.6875rem] tabular-nums text-ad-muted">
                                {{ t('admin.pos.max_cost', { amount: tile.maxCost }) }}
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

    <!-- The variant sheet. One card on the shelf, the choice made here, so the
         grid stays a list of products rather than a list of near-duplicates. -->
    <Modal :open="picking !== null" :title="picking?.title" :footer="null" centered width="420px" @cancel="pickingId = null">
        <ul v-if="picking" class="-mx-2 divide-y divide-ad-line">
            <li v-for="unit in picking.units" :key="unit.key">
                <button
                    type="button"
                    :disabled="unit.available < 1"
                    class="flex w-full items-center gap-3 rounded px-2 py-2.5 text-start transition"
                    :class="unit.available < 1 ? 'cursor-not-allowed opacity-60' : 'hover:bg-ad-hover'"
                    @click="pickUnit(unit)"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-ad-icon-bg">
                        <img v-if="unit.image" :src="unit.image" :alt="unit.subtitle" loading="lazy" class="size-full object-contain" />
                        <ImageOff v-else class="size-4 text-ad-faint" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span dir="auto" class="block truncate text-sm font-medium">{{ unit.subtitle }}</span>
                        <span v-if="unit.variant" dir="auto" class="block truncate text-[0.6875rem] text-ad-muted">{{ unit.variant.sku }}</span>
                    </span>

                    <span class="shrink-0 text-end">
                        <span class="block text-sm font-semibold text-ad-accent">{{ unit.priceFormatted }}</span>
                        <span v-if="unit.available < 1" class="block text-[0.6875rem] font-medium text-ad-danger">
                            {{ t('admin.pos.out_of_stock') }}
                        </span>
                        <span v-else class="block text-[0.6875rem] tabular-nums text-ad-label">
                            {{ t('admin.pos.in_stock_n', { count: unit.available }) }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>
    </Modal>

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
