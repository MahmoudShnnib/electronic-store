---
section: layouts
priority: critical
description: Implement persistent layouts that maintain state across navigation in Vue 3
keywords: [layout, persistent, state, performance, remount, defineOptions, vue3]
---

# Persistent Layouts

Persistent layouts maintain state between page visits in Vue 3. Without them, layouts remount on every navigation, losing state like scroll position, form inputs in navigation, or audio/video playback.

## Incorrect

```vue
<!-- ❌ Layout wrapping in template - remounts on every navigation -->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
</script>

<template>
  <AppLayout>
    <h1>Dashboard</h1>
  </AppLayout>
</template>
```

**Problem:** Layout remounts on every page change, losing any state.

## Correct

### Using defineOptions

```vue
<!-- resources/js/Layouts/AppLayout.vue -->
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const auth = computed(() => page.props.auth as { user?: { name: string } })
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex justify-between items-center">
          <div class="flex space-x-4">
            <Link href="/" class="font-bold">Logo</Link>
            <Link href="/dashboard">Dashboard</Link>
            <Link href="/posts">Posts</Link>
          </div>
          <span>{{ auth.user?.name }}</span>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4">
      <!-- Page content is injected here -->
      <slot />
    </main>
  </div>
</template>
```

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'

// ✅ Assign persistent layout using defineOptions
defineOptions({ layout: AppLayout })
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold">Dashboard</h1>
    <!-- Page content -->
  </div>
</template>
```

### Default Layout in app.ts

```ts
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

### Nested Layouts

```vue
<!-- resources/js/Layouts/SettingsLayout.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="flex">
    <aside class="w-64 border-r p-4">
      <nav class="space-y-2">
        <Link href="/settings/profile">Profile</Link>
        <Link href="/settings/password">Password</Link>
        <Link href="/settings/notifications">Notifications</Link>
      </nav>
    </aside>
    <main class="flex-1 p-6"><slot /></main>
  </div>
</template>
```

```vue
<!-- resources/js/Pages/Settings/Profile.vue -->
<script setup lang="ts">
import { h } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'

// Nested persistent layouts
defineOptions({
  layout: (page: ReturnType<typeof h>) =>
    h(AppLayout, () => h(SettingsLayout, () => page)),
})
</script>

<template>
  <div>
    <h2>Profile Settings</h2>
    <!-- Content -->
  </div>
</template>
```

### Conditional Layouts (Guest vs Auth)

```vue
<!-- resources/js/Pages/Login.vue -->
<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue'

// Different layout for auth pages
defineOptions({ layout: GuestLayout })
</script>

<template>
  <div>
    <h1>Login</h1>
    <!-- Login form -->
  </div>
</template>
```

### Layout Without Persistence (No Layout)

```vue
<!-- When you DON'T want any layout -->
<script setup lang="ts">
defineOptions({ layout: null })
</script>

<template>
  <div>No layout</div>
</template>
```

## Benefits

- State preserved between page navigations
- Audio/video continues playing
- Form inputs in navigation preserved
- Scroll position in sidebars maintained
- Better perceived performance
- Less DOM thrashing on navigation
