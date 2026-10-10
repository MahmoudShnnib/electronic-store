<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Empty, Input, Select, Spin, Tag, Tooltip, Upload } from 'ant-design-vue';
import { ArrowDown, ArrowUp, ExternalLink, ImageOff, ImagePlus, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

/**
 * Curates the storefront homepage: hero slides and the two promo tiles.
 *
 * Both lists are optional. Empty means "automatic" and the storefront keeps
 * choosing for itself, so the page states which mode each block is in rather
 * than leaving an empty list to look like a broken homepage.
 */

interface ProductSummary {
    id: number;
    name: string;
    name_ar: string | null;
    sku: string;
    price_formatted: string;
    image: string | null;
    /** False when the storefront would skip it (draft, hidden, inactive category). */
    visible: boolean;
}

interface CategoryOption {
    id: number;
    name: string;
    name_ar: string | null;
    is_active: boolean;
}

interface Row {
    key: number;
    title: string;
    title_ar: string;
    /** The artwork already saved on the server; kept unless replaced or removed. */
    image_path: string | null;
    image_url: string | null;
    /** A newly chosen file, sent on save. */
    image: File | null;
}

interface SlideRow extends Row {
    product_id: number | null;
    product: ProductSummary | null;
}

interface TileRow extends Row {
    category_id: number | null;
}

const props = defineProps<{
    hero: {
        product_id: number;
        product: ProductSummary | null;
        title: string | null;
        title_ar: string | null;
        image_path: string | null;
        image_url: string | null;
    }[];
    tiles: { category_id: number; title: string | null; title_ar: string | null; image_path: string | null; image_url: string | null }[];
    categories: CategoryOption[];
    limits: { slides: number; tiles: number };
}>();

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

let nextKey = 0;

const slides = ref<SlideRow[]>(
    props.hero.map((slide) => ({
        key: nextKey++,
        product_id: slide.product_id,
        product: slide.product,
        title: slide.title ?? '',
        title_ar: slide.title_ar ?? '',
        image_path: slide.image_path,
        image_url: slide.image_url,
        image: null,
    })),
);

const tiles = ref<TileRow[]>(
    props.tiles.map((tile) => ({
        key: nextKey++,
        category_id: tile.category_id,
        title: tile.title ?? '',
        title_ar: tile.title_ar ?? '',
        image_path: tile.image_path,
        image_url: tile.image_url,
        image: null,
    })),
);

function blankRow(): Row {
    return { key: nextKey++, title: '', title_ar: '', image_path: null, image_url: null, image: null };
}

function addSlide() {
    slides.value.push({ ...blankRow(), product_id: null, product: null });
}

function addTile() {
    tiles.value.push({ ...blankRow(), category_id: null });
}

function move<T>(list: T[], index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
}

/* ---- Artwork ---------------------------------------------------------- */

const previews: string[] = [];

/** Returning false keeps AntD from uploading on its own; the file goes with Save. */
function chooseImage(row: Row, file: File): boolean {
    row.image = file;
    row.image_url = URL.createObjectURL(file);
    previews.push(row.image_url);

    return false;
}

function removeImage(row: Row) {
    row.image = null;
    row.image_path = null;
    row.image_url = null;
}

onBeforeUnmount(() => previews.forEach((url) => URL.revokeObjectURL(url)));

/* ---- Product picker --------------------------------------------------- */

const results = ref<ProductSummary[]>([]);
const searching = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchAbort: AbortController | null = null;

function searchProducts(term: string) {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => runSearch(term), 250);
}

async function runSearch(term: string) {
    searchAbort?.abort();
    searchAbort = new AbortController();
    searching.value = true;

    try {
        const response = await fetch(`${route('admin.homepage.products')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: searchAbort.signal,
        });
        results.value = ((await response.json()) as { products: ProductSummary[] }).products;
    } catch {
        // aborted by a newer keystroke, or offline — keep the last list
    } finally {
        searching.value = false;
    }
}

function productLabel(product: ProductSummary): string {
    return locale.value === 'ar' && product.name_ar ? product.name_ar : product.name;
}

/** The row's own product always stays an option, so the select can show its label. */
function productOptions(row: SlideRow) {
    const pool = row.product && !results.value.some((p) => p.id === row.product!.id) ? [row.product, ...results.value] : results.value;

    return pool.map((product) => ({ value: product.id, label: `${productLabel(product)} · ${product.sku}`, product }));
}

function pickProduct(row: SlideRow, id: unknown) {
    row.product_id = Number(id);
    row.product = results.value.find((p) => p.id === row.product_id) ?? row.product;
}

/* ---- Categories ------------------------------------------------------- */

const categoryOptions = computed(() =>
    props.categories.map((category) => ({
        value: category.id,
        label:
            (locale.value === 'ar' && category.name_ar ? category.name_ar : category.name) +
            (category.is_active ? '' : ` (${t('admin.homepage.inactive_category')})`),
    })),
);

/* ---- Save ------------------------------------------------------------- */

const form = useForm({});

function serialise<T extends Row>(rows: T[], idKey: 'product_id' | 'category_id') {
    return rows
        .filter((row) => (row as unknown as Record<string, number | null>)[idKey] !== null)
        .map((row) => ({
            [idKey]: (row as unknown as Record<string, number>)[idKey],
            title: row.title.trim() || null,
            title_ar: row.title_ar.trim() || null,
            ...(row.image ? { image: row.image } : { image_path: row.image_path }),
        }));
}

function save() {
    form.transform(() => ({
        hero: serialise(slides.value, 'product_id'),
        tiles: serialise(tiles.value, 'category_id'),
    })).post(route('admin.homepage.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            // The server now owns the files; drop the local copies so a second
            // save doesn't upload them again.
            [...slides.value, ...tiles.value].forEach((row) => (row.image = null));
        },
    });
}

/** Server errors arrive keyed by the submitted index, which skips empty rows. */
function errorFor(list: 'hero' | 'tiles', rows: Row[], row: Row, field: string): string | undefined {
    const idKey = list === 'hero' ? 'product_id' : 'category_id';
    const submitted = rows.filter((r) => (r as unknown as Record<string, number | null>)[idKey] !== null);
    const index = submitted.indexOf(row);

    return index === -1 ? undefined : (form.errors as Record<string, string>)[`${list}.${index}.${field}`];
}
</script>

<template>
    <Head :title="t('admin.homepage.title')" />

    <PageHeader :title="t('admin.homepage.title')" :subtitle="t('admin.homepage.subtitle')">
        <template #actions>
            <a :href="route('home')" target="_blank" rel="noopener">
                <Button>
                    <span class="flex items-center gap-2">
                        <ExternalLink class="h-4 w-4" />
                        {{ t('admin.homepage.view_store') }}
                    </span>
                </Button>
            </a>
            <Button type="primary" :loading="form.processing" @click="save">
                {{ form.processing ? t('admin.common.saving') : t('admin.common.save') }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid max-w-5xl gap-6">
        <!-- Hero slides -->
        <Card size="small">
            <template #title>
                <div class="flex items-center gap-2">
                    {{ t('admin.homepage.hero') }}
                    <Tag :color="slides.length ? 'processing' : 'default'" class="!m-0">
                        {{ slides.length ? t('admin.homepage.curated') : t('admin.homepage.automatic') }}
                    </Tag>
                </div>
            </template>
            <template #extra>
                <Tooltip :title="slides.length >= limits.slides ? t('admin.homepage.limit_reached', { max: limits.slides }) : undefined">
                    <Button size="small" :disabled="slides.length >= limits.slides" @click="addSlide">
                        <span class="flex items-center gap-1.5"><Plus class="h-4 w-4" />{{ t('admin.homepage.add_slide') }}</span>
                    </Button>
                </Tooltip>
            </template>

            <p class="admin-muted mb-4 text-sm">{{ t('admin.homepage.hero_hint') }}</p>

            <Empty v-if="slides.length === 0" :description="t('admin.homepage.empty_hero')" />

            <ol v-else class="grid gap-3">
                <li v-for="(slide, index) in slides" :key="slide.key" class="homepage-row">
                    <div class="homepage-thumb">
                        <img v-if="slide.image_url || slide.product?.image" :src="slide.image_url ?? slide.product!.image!" alt="" />
                        <ImageOff v-else class="h-6 w-6 opacity-40" />
                        <span class="homepage-index">{{ index + 1 }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <Field :label="t('admin.homepage.product')" required :error="errorFor('hero', slides, slide, 'product_id')">
                            <Select
                                :value="slide.product_id ?? undefined"
                                show-search
                                :filter-option="false"
                                :options="productOptions(slide)"
                                :placeholder="t('admin.homepage.search_product')"
                                :not-found-content="searching ? undefined : null"
                                class="w-full"
                                @search="searchProducts"
                                @focus="results.length === 0 && runSearch('')"
                                @change="(value: unknown) => pickProduct(slide, value)"
                            >
                                <template v-if="searching" #notFoundContent><Spin size="small" /></template>
                            </Select>
                        </Field>

                        <Alert
                            v-if="slide.product && !slide.product.visible"
                            type="warning"
                            show-icon
                            :message="t('admin.homepage.hidden_warning')"
                            class="!mb-4"
                        />

                        <div class="grid gap-x-4 sm:grid-cols-2">
                            <Field
                                :label="t('admin.homepage.headline')"
                                :hint="t('admin.homepage.headline_hint')"
                                :error="errorFor('hero', slides, slide, 'title')"
                            >
                                <Input v-model:value="slide.title" :maxlength="120" :placeholder="slide.product?.name" />
                            </Field>
                            <Field :label="t('admin.homepage.headline_ar')" :error="errorFor('hero', slides, slide, 'title_ar')">
                                <Input v-model:value="slide.title_ar" :maxlength="120" dir="rtl" :placeholder="slide.product?.name_ar ?? undefined" />
                            </Field>
                        </div>

                        <Field
                            :label="t('admin.homepage.artwork')"
                            :hint="t('admin.homepage.artwork_hint')"
                            :error="errorFor('hero', slides, slide, 'image')"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Upload
                                    :show-upload-list="false"
                                    accept="image/jpeg,image/png,image/webp"
                                    :before-upload="(file: File) => chooseImage(slide, file)"
                                >
                                    <Button size="small">
                                        <span class="flex items-center gap-1.5">
                                            <ImagePlus class="h-4 w-4" />
                                            {{ slide.image_url ? t('admin.homepage.replace') : t('admin.homepage.upload') }}
                                        </span>
                                    </Button>
                                </Upload>
                                <Button v-if="slide.image_url" size="small" type="text" danger @click="removeImage(slide)">
                                    {{ t('admin.homepage.remove_image') }}
                                </Button>
                            </div>
                        </Field>
                    </div>

                    <div class="homepage-actions">
                        <Tooltip :title="t('admin.homepage.move_up')">
                            <Button
                                size="small"
                                type="text"
                                :disabled="index === 0"
                                :aria-label="t('admin.homepage.move_up')"
                                @click="move(slides, index, -1)"
                            >
                                <template #icon><ArrowUp class="h-4 w-4" /></template>
                            </Button>
                        </Tooltip>
                        <Tooltip :title="t('admin.homepage.move_down')">
                            <Button
                                size="small"
                                type="text"
                                :disabled="index === slides.length - 1"
                                :aria-label="t('admin.homepage.move_down')"
                                @click="move(slides, index, 1)"
                            >
                                <template #icon><ArrowDown class="h-4 w-4" /></template>
                            </Button>
                        </Tooltip>
                        <Tooltip :title="t('admin.homepage.remove_slide')">
                            <Button size="small" type="text" danger :aria-label="t('admin.homepage.remove_slide')" @click="slides.splice(index, 1)">
                                <template #icon><Trash2 class="h-4 w-4" /></template>
                            </Button>
                        </Tooltip>
                    </div>
                </li>
            </ol>
        </Card>

        <!-- Promo tiles -->
        <Card size="small">
            <template #title>
                <div class="flex items-center gap-2">
                    {{ t('admin.homepage.tiles') }}
                    <Tag :color="tiles.length ? 'processing' : 'default'" class="!m-0">
                        {{ tiles.length ? t('admin.homepage.curated') : t('admin.homepage.automatic') }}
                    </Tag>
                </div>
            </template>
            <template #extra>
                <Button size="small" :disabled="tiles.length >= limits.tiles" @click="addTile">
                    <span class="flex items-center gap-1.5"><Plus class="h-4 w-4" />{{ t('admin.homepage.add_tile') }}</span>
                </Button>
            </template>

            <p class="admin-muted mb-4 text-sm">{{ t('admin.homepage.tiles_hint') }}</p>

            <Empty v-if="tiles.length === 0" :description="t('admin.homepage.empty_tiles')" />

            <ol v-else class="grid gap-3">
                <li v-for="(tile, index) in tiles" :key="tile.key" class="homepage-row">
                    <div class="homepage-thumb">
                        <img v-if="tile.image_url" :src="tile.image_url" alt="" />
                        <ImageOff v-else class="h-6 w-6 opacity-40" />
                        <span class="homepage-index">{{ index + 1 }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <Field :label="t('admin.homepage.category')" required :error="errorFor('tiles', tiles, tile, 'category_id')">
                            <Select
                                v-model:value="tile.category_id"
                                :options="categoryOptions"
                                :placeholder="t('admin.homepage.choose_category')"
                                show-search
                                option-filter-prop="label"
                                class="w-full"
                            />
                        </Field>

                        <div class="grid gap-x-4 sm:grid-cols-2">
                            <Field :label="t('admin.homepage.headline')" :hint="t('admin.homepage.tile_title_hint')">
                                <Input v-model:value="tile.title" :maxlength="120" />
                            </Field>
                            <Field :label="t('admin.homepage.headline_ar')">
                                <Input v-model:value="tile.title_ar" :maxlength="120" dir="rtl" />
                            </Field>
                        </div>

                        <Field
                            :label="t('admin.homepage.artwork')"
                            :hint="t('admin.homepage.artwork_hint')"
                            :error="errorFor('tiles', tiles, tile, 'image')"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Upload
                                    :show-upload-list="false"
                                    accept="image/jpeg,image/png,image/webp"
                                    :before-upload="(file: File) => chooseImage(tile, file)"
                                >
                                    <Button size="small">
                                        <span class="flex items-center gap-1.5">
                                            <ImagePlus class="h-4 w-4" />
                                            {{ tile.image_url ? t('admin.homepage.replace') : t('admin.homepage.upload') }}
                                        </span>
                                    </Button>
                                </Upload>
                                <Button v-if="tile.image_url" size="small" type="text" danger @click="removeImage(tile)">
                                    {{ t('admin.homepage.remove_image') }}
                                </Button>
                            </div>
                        </Field>
                    </div>

                    <div class="homepage-actions">
                        <Button
                            size="small"
                            type="text"
                            :disabled="index === 0"
                            :aria-label="t('admin.homepage.move_up')"
                            @click="move(tiles, index, -1)"
                        >
                            <template #icon><ArrowUp class="h-4 w-4" /></template>
                        </Button>
                        <Button
                            size="small"
                            type="text"
                            :disabled="index === tiles.length - 1"
                            :aria-label="t('admin.homepage.move_down')"
                            @click="move(tiles, index, 1)"
                        >
                            <template #icon><ArrowDown class="h-4 w-4" /></template>
                        </Button>
                        <Button size="small" type="text" danger :aria-label="t('admin.homepage.remove_slide')" @click="tiles.splice(index, 1)">
                            <template #icon><Trash2 class="h-4 w-4" /></template>
                        </Button>
                    </div>
                </li>
            </ol>
        </Card>

        <div>
            <Button type="primary" :loading="form.processing" @click="save">
                {{ form.processing ? t('admin.common.saving') : t('admin.common.save') }}
            </Button>
        </div>
    </div>
</template>

<style scoped>
.homepage-row {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--ad-line);
    border-radius: var(--ad-radius, 8px);
    background: var(--ad-card);
}

.homepage-thumb {
    position: relative;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 88px;
    height: 88px;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid var(--ad-line);
    background: var(--ad-bg);
}

.homepage-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.homepage-index {
    position: absolute;
    inset-block-start: 4px;
    inset-inline-start: 4px;
    min-width: 1.25rem;
    padding: 0 0.3rem;
    border-radius: 9999px;
    background: var(--ad-primary);
    color: #fff;
    font-size: 0.6875rem;
    font-weight: 700;
    text-align: center;
}

.homepage-actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

@media (max-width: 639px) {
    .homepage-row {
        flex-wrap: wrap;
    }

    .homepage-actions {
        flex-direction: row;
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
