<script setup lang="ts">
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Star, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface ReviewRow {
    id: number;
    product: { id: number; name: string; slug: string } | null;
    customer: { id: number; name: string; email: string } | null;
    rating: number;
    title: string | null;
    body: string | null;
    status: string;
    verified: boolean;
    created_at: string;
}

defineProps<{
    reviews: Paginator<ReviewRow>;
    statuses: Option[];
    filters: Record<string, unknown>;
}>();

function moderate(review: ReviewRow, status: 'approved' | 'rejected'): void {
    router.post(route('admin.reviews.moderate', review.id), { status }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('admin.reviews.title')" />

    <PageHeader :title="t('admin.reviews.title')" :subtitle="t('admin.reviews.count', { count: reviews.total })" />

    <FilterBar
        :url="route('admin.reviews.index')"
        :filters="filters"
        search-key="rating"
        :search-placeholder="t('admin.reviews.filter_rating')"
        :selects="[{ key: 'status', label: t('admin.common.all_statuses'), options: statuses }]"
    />

    <div class="space-y-4">
        <article
            v-for="review in reviews.data"
            :key="review.id"
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex items-center gap-0.5">
                            <Star
                                v-for="i in 5"
                                :key="i"
                                class="h-4 w-4"
                                :class="i <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-300 dark:text-gray-600'"
                            />
                        </span>
                        <StatusBadge :status="review.status" />
                        <span
                            v-if="review.verified"
                            class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium uppercase text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"
                            >{{ t('admin.reviews.verified_purchase') }}</span
                        >
                    </div>
                    <h3 v-if="review.title" class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ review.title }}</h3>
                    <p v-if="review.body" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ review.body }}</p>
                    <p class="mt-2 text-xs text-gray-400">
                        {{ t('admin.reviews.reviewed_on', { name: review.customer?.name ?? t('admin.reviews.unknown') }) }}
                        <Link
                            v-if="review.product"
                            :href="route('products.show', review.product.slug)"
                            class="text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ review.product.name }}
                        </Link>
                        · {{ review.created_at }}
                    </p>
                </div>
                <div v-if="review.status === 'pending'" class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                        @click="moderate(review, 'approved')"
                    >
                        <Check class="h-4 w-4" />{{ t('admin.reviews.approve') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                        @click="moderate(review, 'rejected')"
                    >
                        <X class="h-4 w-4" />{{ t('admin.reviews.reject') }}
                    </button>
                </div>
            </div>
        </article>

        <p
            v-if="reviews.data.length === 0"
            class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-400 dark:border-gray-700 dark:bg-gray-900"
        >
            {{ t('admin.reviews.empty') }}
        </p>
    </div>

    <Pagination :links="reviews.links" :total="reviews.total" :from="reviews.from" :to="reviews.to" />
</template>
