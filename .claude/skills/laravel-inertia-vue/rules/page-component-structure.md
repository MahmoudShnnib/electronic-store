---
section: page-components
priority: critical
description: Standard structure for Inertia page components with TypeScript typing and layout assignment using Vue 3 Composition API
keywords: [inertia, page, component, typescript, layout, vue3, script-setup]
---

# Page Component Structure

Inertia page components should follow a consistent structure with `<script setup lang="ts">`, proper TypeScript typing via `defineProps`, and explicit layout assignment using `defineOptions`.

## Bad Example

```vue
<!-- Anti-pattern: Unstructured component without typing -->
<template>
  <div>
    <h1>Dashboard</h1>
    <p>Welcome {{ props.user.name }}</p>
    <div v-for="stat in props.stats" :key="stat.id">{{ stat.value }}</div>
  </div>
</template>

<script>
export default {
  props: ['user', 'stats']
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatsGrid from '@/Components/StatsGrid.vue'

defineOptions({ layout: AuthenticatedLayout })

interface Stat {
  id: number
  label: string
  value: number
  change: number
}

interface Activity {
  id: number
  description: string
  created_at: string
}

interface Props {
  auth: {
    user: { name: string }
  }
  stats: Stat[]
  recentActivity: Activity[]
}

const props = defineProps<Props>()
</script>

<template>
  <Head title="Dashboard" />

  <div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
      <h1 class="text-2xl font-semibold text-gray-900">
        Welcome back, {{ props.auth.user.name }}
      </h1>

      <StatsGrid :stats="props.stats" />

      <RecentActivityList :activities="props.recentActivity" />
    </div>
  </div>
</template>
```

## Why

A well-structured page component provides several benefits:

1. **Type Safety**: TypeScript interfaces catch prop mismatches at compile time rather than runtime
2. **Maintainability**: Clear structure makes it easy to understand what data the page expects
3. **Reusability**: Extracting sub-components keeps pages focused on composition
4. **SEO**: Using the Head component ensures proper meta tags for each page
5. **Layout Consistency**: `defineOptions({ layout })` prevents layout-related bugs
6. **Developer Experience**: Consistent patterns across pages reduce cognitive load
7. **Vue 3 Idioms**: `<script setup>` is the recommended modern Vue 3 syntax
