<script setup lang="ts">
/**
 * One storefront page in the page builder: its own fields (titles, slug,
 * SEO, published) and its ordered sections. Sections are dragged (or moved
 * with the arrow buttons) to reorder, switched on/off in place, and edited in
 * a drawer. The SPA reads the result from GET /api/v1/pages/{key}.
 */
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import SectionDrawer from '@/components/admin/content/SectionDrawer.vue';
import type { BuilderLimits, CategoryOption, ProductSummary, Section, SectionType } from '@/components/admin/content/types';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Button, Card, Dropdown, Empty, Input, Menu, Switch, Tag, Textarea, Tooltip } from 'ant-design-vue';
import { ArrowDown, ArrowLeft, ArrowUp, Braces, GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    page: {
        id: number;
        key: string | null;
        slug: string | null;
        title: string;
        title_ar: string | null;
        is_published: boolean;
        is_system: boolean;
        seo: Record<string, string>;
    };
    sections: Section[];
    products: Record<number, ProductSummary>;
    categories: CategoryOption[];
    types: SectionType[];
    sources: string[];
    storage_url: string;
    limits: BuilderLimits;
    api_url: string;
}>();

const { t } = useI18n();
const inertia = usePage();
const locale = computed(() => inertia.props.locale as string);

/* ---- Page fields ------------------------------------------------------ */

const pageForm = useForm({
    title: props.page.title,
    title_ar: props.page.title_ar ?? '',
    slug: props.page.slug ?? '',
    is_published: props.page.is_published,
    seo: {
        title: props.page.seo.title ?? '',
        title_ar: props.page.seo.title_ar ?? '',
        description: props.page.seo.description ?? '',
        description_ar: props.page.seo.description_ar ?? '',
    },
});

function savePage(): void {
    pageForm
        .transform((data) => {
            if (!props.page.is_system) return data;
            const { slug: _slug, ...rest } = data;
            return rest;
        })
        .put(route('admin.pages.update', props.page.id), { preserveScroll: true });
}

const pageErrors = computed(() => pageForm.errors as Record<string, string>);

/* ---- Sections --------------------------------------------------------- */

// A local copy so a drag shows at once; the server's order replaces it
// after every visit.
const list = ref<Section[]>([...props.sections]);
watch(
    () => props.sections,
    (sections) => (list.value = [...sections]),
);

const knownProducts = reactive<Record<number, ProductSummary>>({ ...props.products });
watch(
    () => props.products,
    (products) => Object.assign(knownProducts, products),
);

function rememberProducts(products: ProductSummary[]): void {
    products.forEach((product) => (knownProducts[product.id] = product));
}

const typeLabel = (type: SectionType) => t(`admin.pages.types.${type}`);

const name = (item: { name: string; name_ar: string | null }) => (locale.value === 'ar' && item.name_ar ? item.name_ar : item.name);

/** One line under the section's name saying what it will show. */
function summary(section: Section): string {
    const data = section.data;
    const heading = (locale.value === 'ar' ? data.title_ar || data.title : data.title || data.title_ar) as string | undefined;

    switch (section.type) {
        case 'hero':
            return data.slides?.length ? t('admin.pages.summary.slides', { n: data.slides.length }) : t('admin.homepage.automatic');
        case 'promo_tiles':
            return data.tiles?.length ? t('admin.pages.summary.tiles', { n: data.tiles.length }) : t('admin.homepage.automatic');
        case 'trust_strip':
            return t('admin.pages.summary.items', { n: data.items?.length ?? 0 });
        case 'product_carousel': {
            const category = props.categories.find((c) => c.id === data.category_id);
            const source = t(`admin.pages.sources.${data.source}`) + (category ? ` · ${name(category)}` : '');
            return [heading, source, t('admin.pages.summary.limit', { n: data.limit })].filter(Boolean).join(' — ');
        }
        case 'image_banner':
            return [heading, data.link].filter(Boolean).join(' — ') || t('admin.pages.summary.banner');
        case 'rich_text':
            return String(data.body ?? data.body_ar ?? '')
                .replace(/<[^>]+>/g, ' ')
                .trim()
                .slice(0, 80);
        default:
            return heading ?? '';
    }
}

/* ---- Reorder (drag, or the arrow buttons) ----------------------------- */

const dragging = ref<number | null>(null);
const over = ref<number | null>(null);

function persistOrder(): void {
    router.post(
        route('admin.pages.sections.reorder', props.page.id),
        { ids: list.value.map((section) => section.id) },
        { preserveScroll: true, preserveState: true, onError: () => (list.value = [...props.sections]) },
    );
}

function onDragStart(event: DragEvent, index: number, id: number): void {
    dragging.value = index;
    event.dataTransfer?.setData('text/plain', String(id));
}

function onDrop(index: number): void {
    const from = dragging.value;
    dragging.value = over.value = null;
    if (from === null || from === index) return;

    const [moved] = list.value.splice(from, 1);
    list.value.splice(index, 0, moved);
    persistOrder();
}

function move(index: number, delta: number): void {
    const target = index + delta;
    if (target < 0 || target >= list.value.length) return;
    [list.value[index], list.value[target]] = [list.value[target], list.value[index]];
    persistOrder();
}

/* ---- Toggle / delete -------------------------------------------------- */

const toggling = ref<number | null>(null);

function toggle(section: Section): void {
    toggling.value = section.id;
    router.patch(
        route('admin.pages.sections.toggle', [props.page.id, section.id]),
        {},
        { preserveScroll: true, onFinish: () => (toggling.value = null) },
    );
}

const deleting = ref<Section | null>(null);
const deletingBusy = ref(false);

function destroy(): void {
    if (!deleting.value) return;
    deletingBusy.value = true;
    router.delete(route('admin.pages.sections.destroy', [props.page.id, deleting.value.id]), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = null;
            deletingBusy.value = false;
        },
    });
}

/* ---- Drawer ----------------------------------------------------------- */

const drawer = reactive<{ open: boolean; section: Section | null; type: SectionType | null }>({ open: false, section: null, type: null });

function edit(section: Section): void {
    Object.assign(drawer, { open: true, section, type: null });
}

function add(type: SectionType): void {
    Object.assign(drawer, { open: true, section: null, type });
}

function onAddClick(info: { key: string | number }): void {
    add(info.key as SectionType);
}

const addMenu = computed(() => props.types.map((type) => ({ key: type, label: typeLabel(type) })));
</script>

<template>
    <Head :title="page.title" />

    <PageHeader :title="page.title" :subtitle="page.is_system ? t('admin.pages.system_page', { key: page.key }) : `/${page.slug}`">
        <template #actions>
            <Link :href="route('admin.pages.index')">
                <Button>
                    <span class="flex items-center gap-2"><ArrowLeft class="h-4 w-4 rtl:rotate-180" />{{ t('admin.common.back_to_list') }}</span>
                </Button>
            </Link>
            <a :href="api_url" target="_blank" rel="noopener">
                <Button>
                    <span class="flex items-center gap-2"><Braces class="h-4 w-4" />{{ t('admin.pages.view_api') }}</span>
                </Button>
            </a>
        </template>
    </PageHeader>

    <div class="grid max-w-5xl gap-6">
        <Card size="small" :title="t('admin.pages.sections')">
            <template #extra>
                <Dropdown :trigger="['click']">
                    <Button type="primary" size="small">
                        <span class="flex items-center gap-1.5"><Plus class="h-4 w-4" />{{ t('admin.pages.add_section') }}</span>
                    </Button>
                    <template #overlay>
                        <Menu :items="addMenu" @click="onAddClick" />
                    </template>
                </Dropdown>
            </template>

            <p class="admin-muted mb-4 text-sm">{{ t('admin.pages.sections_hint') }}</p>

            <Empty v-if="list.length === 0" :description="t('admin.pages.no_sections')" />

            <ol v-else class="grid gap-2">
                <li
                    v-for="(section, index) in list"
                    :key="section.id"
                    class="section-row"
                    :class="{ 'is-off': !section.is_active, 'is-dragging': dragging === index, 'is-over': over === index && dragging !== index }"
                    draggable="true"
                    @dragstart="(event: DragEvent) => onDragStart(event, index, section.id)"
                    @dragover.prevent="over = index"
                    @dragleave="over === index && (over = null)"
                    @drop.prevent="onDrop(index)"
                    @dragend="dragging = over = null"
                >
                    <GripVertical class="section-grip h-4 w-4" aria-hidden="true" />
                    <span class="section-index">{{ index + 1 }}</span>

                    <button type="button" class="min-w-0 flex-1 text-start" @click="edit(section)">
                        <span class="block font-medium">{{ typeLabel(section.type) }}</span>
                        <span class="admin-muted block truncate text-xs" dir="auto">{{ summary(section) || '—' }}</span>
                    </button>

                    <Tag v-if="!section.is_active" class="!m-0">{{ t('admin.pages.hidden') }}</Tag>

                    <div class="flex items-center gap-1">
                        <Tooltip :title="section.is_active ? t('admin.pages.hide') : t('admin.pages.show')">
                            <Switch
                                :checked="section.is_active"
                                size="small"
                                :loading="toggling === section.id"
                                :aria-label="section.is_active ? t('admin.pages.hide') : t('admin.pages.show')"
                                @change="toggle(section)"
                            />
                        </Tooltip>
                        <Button size="small" type="text" :disabled="index === 0" :aria-label="t('admin.homepage.move_up')" @click="move(index, -1)">
                            <template #icon><ArrowUp class="h-4 w-4" /></template>
                        </Button>
                        <Button
                            size="small"
                            type="text"
                            :disabled="index === list.length - 1"
                            :aria-label="t('admin.homepage.move_down')"
                            @click="move(index, 1)"
                        >
                            <template #icon><ArrowDown class="h-4 w-4" /></template>
                        </Button>
                        <Tooltip :title="t('admin.common.edit')">
                            <Button size="small" type="text" :aria-label="t('admin.common.edit')" @click="edit(section)">
                                <template #icon><Pencil class="h-4 w-4" /></template>
                            </Button>
                        </Tooltip>
                        <Tooltip :title="t('admin.common.delete')">
                            <Button size="small" type="text" danger :aria-label="t('admin.common.delete')" @click="deleting = section">
                                <template #icon><Trash2 class="h-4 w-4" /></template>
                            </Button>
                        </Tooltip>
                    </div>
                </li>
            </ol>
        </Card>

        <Card size="small" :title="t('admin.pages.page_settings')">
            <form @submit.prevent="savePage">
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <Field :label="t('admin.pages.title_en')" required :error="pageForm.errors.title">
                        <Input v-model:value="pageForm.title" :maxlength="120" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.pages.title_ar')" :error="pageForm.errors.title_ar">
                        <Input v-model:value="pageForm.title_ar" :maxlength="120" dir="rtl" />
                    </Field>
                    <Field
                        v-if="!page.is_system"
                        :label="t('admin.common.slug')"
                        required
                        :hint="t('admin.pages.slug_hint')"
                        :error="pageForm.errors.slug"
                    >
                        <Input v-model:value="pageForm.slug" :maxlength="80" dir="ltr" />
                    </Field>
                    <Field
                        :label="t('admin.pages.published')"
                        :hint="page.is_system ? t('admin.pages.system_always_published') : undefined"
                        :error="pageForm.errors.is_published"
                    >
                        <Switch v-model:checked="pageForm.is_published" :disabled="page.is_system" />
                    </Field>
                    <Field :label="t('admin.pages.seo_title')" :error="pageErrors['seo.title']">
                        <Input v-model:value="pageForm.seo.title" :maxlength="120" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.pages.seo_title_ar')" :error="pageErrors['seo.title_ar']">
                        <Input v-model:value="pageForm.seo.title_ar" :maxlength="120" dir="rtl" />
                    </Field>
                    <Field :label="t('admin.pages.seo_description')" :error="pageErrors['seo.description']">
                        <Textarea v-model:value="pageForm.seo.description" :maxlength="300" :rows="2" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.pages.seo_description_ar')" :error="pageErrors['seo.description_ar']">
                        <Textarea v-model:value="pageForm.seo.description_ar" :maxlength="300" :rows="2" dir="rtl" />
                    </Field>
                </div>
                <Button type="primary" html-type="submit" :loading="pageForm.processing">
                    {{ pageForm.processing ? t('admin.common.saving') : t('admin.common.save') }}
                </Button>
            </form>
        </Card>
    </div>

    <SectionDrawer
        :open="drawer.open"
        :page-id="page.id"
        :section="drawer.section"
        :type="drawer.type"
        :products="knownProducts"
        :categories="categories"
        :sources="sources"
        :limits="limits"
        :storage-url="storage_url"
        @products="rememberProducts"
        @close="drawer.open = false"
    />

    <ConfirmDialog
        :open="deleting !== null"
        :message="deleting ? t('admin.pages.delete_section_confirm', { type: typeLabel(deleting.type) }) : ''"
        :confirm-label="t('admin.common.delete')"
        danger
        :processing="deletingBusy"
        @confirm="destroy"
        @close="deleting = null"
    />
</template>

<style scoped>
.section-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--ad-line);
    border-radius: 8px;
    background: var(--ad-card);
    transition:
        border-color 120ms,
        opacity 120ms;
}

.section-row.is-off {
    opacity: 0.6;
}

.section-row.is-dragging {
    opacity: 0.4;
}

.section-row.is-over {
    border-color: var(--ad-primary);
    box-shadow: 0 0 0 1px var(--ad-primary);
}

.section-grip {
    flex-shrink: 0;
    cursor: grab;
    opacity: 0.5;
}

.section-index {
    flex-shrink: 0;
    min-width: 1.5rem;
    padding: 0 0.35rem;
    border-radius: 9999px;
    background: var(--ad-bg);
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
}

@media (max-width: 639px) {
    .section-row {
        flex-wrap: wrap;
    }
}
</style>
