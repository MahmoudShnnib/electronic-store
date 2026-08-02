---
section: navigation
priority: critical
description: Use Link component for SPA-like navigation without full page reloads in Vue 3
keywords: [Link, navigation, spa, router, link-component, vue3]
---

# Navigation Link Component

Use Inertia's Link component for internal navigation to enable SPA-like page transitions without full page reloads.

## Bad Example

```vue
<!-- Anti-pattern: Using regular anchor tags -->
<template>
  <nav>
    <a href="/dashboard">Dashboard</a>
    <a href="/users">Users</a>
    <a :href="`/users/${user.id}`">Profile</a>
  </nav>
</template>

<!-- Anti-pattern: Manual navigation on click -->
<template>
  <button @click="() => window.location.href = '/dashboard'">Dashboard</button>
</template>
```

## Good Example

```vue
<!-- resources/js/Components/NavLink.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

interface Props {
  href: string
  active?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  active: false,
})
</script>

<template>
  <Link
    :href="props.href"
    :class="[
      'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none',
      props.active
        ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
    ]"
  >
    <slot />
  </Link>
</template>
```

```vue
<!-- resources/js/Layouts/AuthenticatedLayout.vue -->
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import NavLink from '@/Components/NavLink.vue'
import { computed } from 'vue'

const page = usePage()
const currentUrl = computed(() => page.url)
</script>

<template>
  <div>
    <nav class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
          <div class="flex">
            <!-- Logo/Home link -->
            <Link :href="route('home')" class="flex items-center">
              <ApplicationLogo class="h-9 w-auto" />
            </Link>

            <!-- Navigation Links -->
            <div class="hidden space-x-8 sm:ml-10 sm:flex">
              <NavLink
                :href="route('dashboard')"
                :active="route().current('dashboard')"
              >
                Dashboard
              </NavLink>

              <NavLink
                :href="route('projects.index')"
                :active="route().current('projects.*')"
              >
                Projects
              </NavLink>

              <NavLink
                :href="route('users.index')"
                :active="route().current('users.*')"
              >
                Users
              </NavLink>
            </div>
          </div>

          <!-- User actions -->
          <div class="flex items-center">
            <Link
              :href="route('profile.edit')"
              class="text-sm text-gray-700 hover:text-gray-900"
            >
              Profile
            </Link>

            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="ml-4 text-sm text-gray-700 hover:text-gray-900"
            >
              Log Out
            </Link>
          </div>
        </div>
      </div>
    </nav>

    <main><slot /></main>
  </div>
</template>
```

```vue
<!-- Advanced Link usage examples -->
<template>
  <div class="space-y-4">
    <!-- Basic link -->
    <Link href="/users">Users</Link>

    <!-- Using Ziggy routes -->
    <Link :href="route('users.show', { user: 1 })">View User</Link>

    <!-- Preserve scroll position -->
    <Link href="/users?page=2" :preserve-scroll="true">Next Page</Link>

    <!-- Replace history instead of push -->
    <Link href="/users" replace>Users (replace)</Link>

    <!-- Preserve component state -->
    <Link href="/users?filter=active" :preserve-state="true">Active Users</Link>

    <!-- Only reload specific props -->
    <Link href="/dashboard" :only="['notifications']">Refresh Notifications</Link>

    <!-- POST request as link -->
    <Link
      :href="route('posts.favorite', { post: 1 })"
      method="post"
      as="button"
      class="text-blue-600 hover:underline"
    >
      Add to Favorites
    </Link>

    <!-- DELETE with confirmation -->
    <Link
      :href="route('posts.destroy', { post: 1 })"
      method="delete"
      as="button"
      @before="() => confirm('Are you sure?')"
      class="text-red-600 hover:underline"
    >
      Delete Post
    </Link>
  </div>
</template>
```

## Why

Using Inertia's Link component is essential for proper SPA behavior:

1. **No Full Reload**: Pages transition smoothly without browser refresh
2. **State Preservation**: Vue component state persists across navigation
3. **Prefetching**: Inertia can prefetch pages on hover for faster navigation
4. **Progress Indicator**: Integrates with Inertia's loading progress bar
5. **HTTP Methods**: Support for POST, PUT, PATCH, DELETE via method prop
6. **Ziggy Integration**: Works seamlessly with Laravel's named routes
7. **History Management**: Proper browser back/forward button behavior
8. **Accessibility**: Renders semantic anchor tags with proper href attributes
