---
section: navigation
priority: high
description: Use replace option to prevent cluttered browser history in Vue 3
keywords: [replace, history, browser, navigation, filters, tabs, vue3]
---

# Navigation Replace History

Use the replace option to modify the current history entry instead of adding a new one, preventing cluttered browser history.

## Bad Example

```vue
<!-- Anti-pattern: Every filter change adds to history -->
<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps(['products', 'filters'])

function handleFilterChange(key, value) {
  // Each change pushes to history - back button becomes unusable
  router.get(route('products.index'), { ...props.filters, [key]: value })
}

function handleSearchInput(search) {
  // Every keystroke adds history entry!
  router.get(route('products.index'), { search })
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Products/Index.vue -->
<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3'
import { ref, onUnmounted } from 'vue'

interface Product {
  id: number
  name: string
  price: number
}

interface PaginatedData<T> {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
}

interface Filters {
  search: string
  category: string
  sort: string
  min_price: string
  max_price: string
}

interface Category {
  id: number
  name: string
  slug: string
}

interface Props {
  products: PaginatedData<Product>
  filters: Filters
  categories: Category[]
}

const props = defineProps<Props>()
const localSearch = ref(props.filters.search)
let debounceTimer: ReturnType<typeof setTimeout>

// Debounced search that replaces history
function handleSearchChange(e: Event) {
  const value = (e.target as HTMLInputElement).value
  localSearch.value = value

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(
      route('products.index'),
      { ...props.filters, search: value },
      {
        replace: true,       // Don't add history entry for each keystroke
        preserveState: true,
        preserveScroll: true,
      }
    )
  }, 300)
}

onUnmounted(() => clearTimeout(debounceTimer))

// Filter changes should replace history
function updateFilter(key: string, value: string) {
  router.get(
    route('products.index'),
    { ...props.filters, [key]: value },
    {
      replace: true,         // Replace instead of push
      preserveState: true,
      preserveScroll: true,
    }
  )
}

// Clear all filters - this SHOULD add history (intentional action)
function clearFilters() {
  router.get(route('products.index'), {}, { replace: false })
}
</script>

<template>
  <div>
    <!-- Search input - replaces history -->
    <input
      type="search"
      :value="localSearch"
      placeholder="Search products..."
      class="rounded-md border-gray-300"
      @input="handleSearchChange"
    />

    <!-- Category filter - replaces history -->
    <select
      :value="props.filters.category"
      class="rounded-md border-gray-300"
      @change="(e) => updateFilter('category', (e.target as HTMLSelectElement).value)"
    >
      <option value="">All Categories</option>
      <option
        v-for="category in props.categories"
        :key="category.id"
        :value="category.slug"
      >
        {{ category.name }}
      </option>
    </select>

    <!-- Sort - replaces history -->
    <select
      :value="props.filters.sort"
      class="rounded-md border-gray-300"
      @change="(e) => updateFilter('sort', (e.target as HTMLSelectElement).value)"
    >
      <option value="newest">Newest</option>
      <option value="price_asc">Price: Low to High</option>
      <option value="price_desc">Price: High to Low</option>
      <option value="popular">Most Popular</option>
    </select>

    <!-- Clear filters - adds to history -->
    <button class="text-blue-600 hover:underline" @click="clearFilters">
      Clear All Filters
    </button>

    <!-- Product grid -->
    <div class="grid grid-cols-3 gap-4">
      <ProductCard
        v-for="product in props.products.data"
        :key="product.id"
        :product="product"
      />
    </div>

    <!-- Pagination - adds to history (intentional navigation) -->
    <div class="flex gap-2">
      <Link
        v-for="(link, index) in props.products.links"
        :key="index"
        :href="link.url || '#'"
        :replace="false"
        :class="link.active ? 'font-bold' : ''"
        v-html="link.label"
      />
    </div>
  </div>
</template>
```

```vue
<!-- Tab navigation - replace history for tab switches -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const props = defineProps<{ activeTab: string }>()

function switchTab(tab: string) {
  router.get(
    route('content.show'),
    { tab },
    {
      replace: true,         // Tab switches replace history
      preserveState: true,
    }
  )
}
</script>

<template>
  <div>
    <div class="flex border-b">
      <button
        v-for="tab in ['overview', 'details', 'reviews']"
        :key="tab"
        :class="props.activeTab === tab ? 'border-b-2 border-blue-500' : ''"
        @click="switchTab(tab)"
      >
        {{ tab }}
      </button>
    </div>
  </div>
</template>
```

## Why

Using replace for history management provides:

1. **Clean History**: Back button takes users to logical previous pages
2. **Filter UX**: Multiple filter changes don't pollute browser history
3. **Search Experience**: Typing in search doesn't create dozens of history entries
4. **Intentional Actions**: Use push (default) for deliberate navigation actions
5. **Tab Navigation**: Switching tabs shouldn't add to history stack
6. **Performance**: Fewer history entries means less memory usage
7. **User Expectations**: History behaves like traditional websites
