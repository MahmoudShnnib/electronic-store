---
section: page-components
priority: high
description: Control scroll behavior during navigation with preserveScroll option in Vue 3
keywords: [scroll, navigation, pagination, ux, preserveScroll]
---

# Page Scroll Preservation

Control scroll behavior during Inertia navigation to maintain user context and provide smooth transitions.

## Bad Example

```vue
<!-- Anti-pattern: Manual scroll management with onMounted -->
<script setup>
import { onMounted } from 'vue'

const props = defineProps(['products'])

onMounted(() => {
  window.scrollTo(0, 0)
})
</script>

<!-- Anti-pattern: Using Link without considering scroll behavior -->
<template>
  <div>
    <Link v-for="link in links" :key="link.label" :href="link.url">
      {{ link.label }}
    </Link>
  </div>
</template>
```

## Good Example

```vue
<!-- resources/js/Pages/Products/Index.vue -->
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

interface Product {
  id: number
  name: string
  price: number
}

interface Filters {
  sort?: string
  search?: string
}

interface Props {
  products: Product[]
  filters: Filters
}

const props = defineProps<Props>()

// Preserve scroll position when filtering/sorting
function handleSort(sortBy: string) {
  router.get(
    route('products.index'),
    { ...props.filters, sort: sortBy },
    { preserveScroll: true }
  )
}
</script>

<template>
  <div>
    <select @change="(e) => handleSort((e.target as HTMLSelectElement).value)">
      <option value="name">Name</option>
      <option value="price">Price</option>
    </select>

    <div v-for="product in props.products" :key="product.id">
      <ProductCard :product="product" />
    </div>

    <!-- Preserve scroll for pagination -->
    <Pagination :links="links" :preserve-scroll="true" />
  </div>
</template>
```

```vue
<!-- Pagination component with scroll preservation -->
<!-- resources/js/Components/Pagination.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface Props {
  links: PaginationLink[]
  preserveScroll?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  preserveScroll: false,
})
</script>

<template>
  <div class="flex gap-2">
    <Link
      v-for="(link, index) in props.links"
      :key="index"
      :href="link.url ?? '#'"
      :preserve-scroll="props.preserveScroll"
      :class="link.active ? 'font-bold' : ''"
      v-html="link.label"
    />
  </div>
</template>
```

```vue
<!-- Scroll to specific element after navigation -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

function jumpToSection(sectionId: string) {
  router.visit(route('page.show'), {
    onSuccess: () => {
      document.getElementById(sectionId)?.scrollIntoView({
        behavior: 'smooth',
      })
    },
  })
}
</script>
```

## Why

Proper scroll management improves user experience significantly:

1. **Context Preservation**: Users don't lose their place when filtering or sorting data
2. **Pagination UX**: Scroll preservation during pagination keeps users oriented in long lists
3. **Form Interactions**: Preserving scroll after form submissions feels more natural
4. **Navigation Clarity**: Scrolling to top on new pages signals a fresh context
5. **Deep Linking**: Proper scroll handling supports linking to specific page sections
6. **Performance Perception**: Smooth scroll behavior makes the app feel more responsive
