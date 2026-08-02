---
section: page-components
priority: critical
description: Manage document head with title and meta tags using Inertia's Head component in Vue 3
keywords: [head, title, meta, seo, document, open-graph, vue3]
---

# Page Head Management

Use Inertia's Head component to manage document head elements like title, meta tags, and Open Graph data on a per-page basis.

## Bad Example

```vue
<!-- Anti-pattern: Using document.title directly -->
<script setup>
import { onMounted, watch } from 'vue'

const props = defineProps(['product'])

onMounted(() => {
  document.title = props.product.name + ' | My Store'
})

watch(() => props.product.name, (name) => {
  document.title = name + ' | My Store'
})
</script>

<!-- Anti-pattern: Missing head management entirely -->
<template>
  <div>
    <h1>{{ product.name }}</h1>
  </div>
</template>
```

## Good Example

```vue
<!-- resources/js/Pages/Products/Show.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

interface Product {
  id: number
  name: string
  description: string
  price: number
  image_url: string
  category: string
}

interface Props {
  product: Product
}

const props = defineProps<Props>()

const truncatedDescription = props.product.description.substring(0, 160)
</script>

<template>
  <Head>
    <title>{{ props.product.name }}</title>
    <meta name="description" :content="truncatedDescription" />

    <!-- Open Graph -->
    <meta property="og:title" :content="props.product.name" />
    <meta property="og:description" :content="truncatedDescription" />
    <meta property="og:image" :content="props.product.image_url" />
    <meta property="og:type" content="product" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" :content="props.product.name" />
    <meta name="twitter:description" :content="truncatedDescription" />
    <meta name="twitter:image" :content="props.product.image_url" />
  </Head>

  <div class="product-page">
    <h1>{{ props.product.name }}</h1>
    <p>{{ props.product.description }}</p>
  </div>
</template>
```

```vue
<!-- Simple usage with title shorthand -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
</script>

<template>
  <Head title="About Us" />
  <div>About page content</div>
</template>
```

## Why

Using Inertia's Head component is crucial for:

1. **SEO**: Search engines need proper titles and meta descriptions to index pages correctly
2. **Social Sharing**: Open Graph and Twitter Card meta tags control how pages appear when shared
3. **SSR Compatibility**: Head component works with server-side rendering, unlike direct DOM manipulation
4. **Automatic Cleanup**: Inertia automatically removes head elements when navigating away
5. **Template Support**: You can set a title template in app.ts like `titleTemplate="%s | My App"`
6. **Accessibility**: Proper page titles help screen reader users understand page context
