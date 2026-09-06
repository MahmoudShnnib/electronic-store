<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import Pagination from '@/components/store/Pagination.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import type { PaginationLink, ProductCardData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{
    products: { data: ProductCardData[]; meta: { links: PaginationLink[]; total: number } };
}>();

const { Title } = Typography;

const { t } = useI18n();
</script>

<template>
    <Head :title="t('wishlist.title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <Title :level="1" class="!mb-6 !text-2xl !font-bold">{{ t('wishlist.title') }}</Title>

        <EmptyState
            v-if="products.data.length === 0"
            :message="t('wishlist.empty')"
            :action-label="t('cart.continue_shopping')"
            :action-href="route('catalog.index')"
        />

        <template v-else>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="product in products.data" :key="product.id" :product="product" />
            </div>
            <Pagination :links="products.meta.links" />
        </template>
    </div>
</template>
