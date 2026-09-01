<script setup lang="ts">
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Card, Empty, Rate, Space, Tag, Typography } from 'ant-design-vue';
import { Check, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const { Title, Text } = Typography;

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
        <Card v-for="review in reviews.data" :key="review.id" size="small">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Space :size="8" align="center">
                        <Rate :value="review.rating" :count="5" disabled class="!text-sm" />
                        <StatusBadge :status="review.status" />
                        <Tag v-if="review.verified" color="green">{{ t('admin.reviews.verified_purchase') }}</Tag>
                    </Space>
                    <Title v-if="review.title" :level="5" class="!mb-0 !mt-2 !text-sm">{{ review.title }}</Title>
                    <p v-if="review.body" class="mb-0 mt-1 text-sm">{{ review.body }}</p>
                    <Text type="secondary" class="mt-2 block !text-xs">
                        {{ t('admin.reviews.reviewed_on', { name: review.customer?.name ?? t('admin.reviews.unknown') }) }}
                        <Link
                            v-if="review.product"
                            :href="route('products.show', review.product.slug)"
                            class="text-blue-600 hover:underline dark:text-blue-400"
                        >
                            {{ review.product.name }}
                        </Link>
                        · {{ review.created_at }}
                    </Text>
                </div>
                <Space v-if="review.status === 'pending'">
                    <Button type="primary" @click="moderate(review, 'approved')">
                        <template #icon><Check class="h-4 w-4" /></template>
                        {{ t('admin.reviews.approve') }}
                    </Button>
                    <Button danger @click="moderate(review, 'rejected')">
                        <template #icon><X class="h-4 w-4" /></template>
                        {{ t('admin.reviews.reject') }}
                    </Button>
                </Space>
            </div>
        </Card>

        <!-- The list is made of cards, so the empty state is one too — otherwise it
             floats on the page background with nothing holding it. -->
        <Card v-if="reviews.data.length === 0" size="small">
            <Empty :description="t('admin.reviews.empty')" class="!my-6" />
        </Card>
    </div>

    <Pagination :paginator="reviews" />
</template>
