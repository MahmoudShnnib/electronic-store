---
section: page-components
priority: critical
description: Assign layouts using defineOptions for persistent layouts in Vue 3
keywords: [layout, persistent, page, defineOptions, assignment]
---

# Page Default Layout

Assign layouts to pages using `defineOptions({ layout })` to enable persistent layouts and avoid unnecessary re-renders.

## Bad Example

```vue
<!-- Anti-pattern: Wrapping layout inside the template -->
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps<{ auth: { user: { name: string } } }>()
</script>

<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <h1>Dashboard</h1>
    </div>
  </AuthenticatedLayout>
</template>
```

## Good Example

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({ layout: AuthenticatedLayout })

interface Props {
  auth: {
    user: { name: string }
  }
}

const props = defineProps<Props>()
</script>

<template>
  <Head title="Dashboard" />
  <div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
      <h1>Welcome, {{ props.auth.user.name }}</h1>
    </div>
  </div>
</template>
```

```vue
<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 py-3">
        <Link href="/" class="font-bold">My App</Link>
      </div>
    </nav>

    <main class="py-6">
      <!-- Page content is injected here via the default slot -->
      <slot />
    </main>
  </div>
</template>
```

```ts
// Default layout in app.ts (applies to all pages without explicit layout)
// resources/js/app.ts
import { createApp, h, DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

createInertiaApp({
  resolve: async (name) => {
    const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue')
    const page = await pages[`./Pages/${name}.vue`]()

    // Set default layout if page doesn't define one
    page.default.layout = page.default.layout ?? AppLayout

    return page
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
```

## Why

Using `defineOptions({ layout })` is important because:

1. **Persistent Layouts**: Layouts remain mounted between page visits, preserving their state
2. **Performance**: Audio players, video, scroll position, and form state persist across navigation
3. **Fewer Re-renders**: The layout doesn't unmount/remount on every page change
4. **Cleaner Components**: Page components focus on their content, not layout wrapping
5. **Consistency**: All pages follow the same pattern, making the codebase predictable
6. **Flexibility**: Easy to switch layouts or use conditional layouts per page
