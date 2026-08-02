---
section: shared-data
priority: critical
description: Use Ziggy for type-safe Laravel route generation in Vue 3
keywords: [ziggy, routes, routing, laravel, named-routes, type-safety, vue3]
---

# Shared Ziggy Routes

Use Ziggy to share Laravel named routes with your Vue 3 frontend, enabling type-safe route generation with parameters.

## Bad Example

```vue
<!-- Anti-pattern: Hardcoding URLs -->
<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps(['users'])
</script>

<template>
  <ul>
    <li v-for="user in props.users" :key="user.id">
      <Link :href="`/users/${user.id}`">{{ user.name }}</Link>
      <Link :href="`/users/${user.id}/edit`">Edit</Link>
    </li>
  </ul>
</template>
```

## Good Example

```ts
// Install Ziggy: composer require tightenco/ziggy
// Add to your blade template: @routes

// resources/js/types/ziggy.d.ts
declare global {
  function route(name: string, params?: Record<string, unknown>, absolute?: boolean): string
  function route(): { current: (name?: string, params?: unknown) => boolean | string; has: (name: string) => boolean }
}

export {}
```

```ts
// Generate types with: php artisan ziggy:generate
// resources/js/types/ziggy-routes.d.ts (auto-generated)
interface ZiggyRoutes {
  'home': []
  'dashboard': []
  'users.index': []
  'users.create': []
  'users.store': []
  'users.show': [{ user: number | string }]
  'users.edit': [{ user: number | string }]
  'users.update': [{ user: number | string }]
  'users.destroy': [{ user: number | string }]
  'posts.index': []
  'posts.show': [{ post: number | string }]
  'profile.edit': []
  'profile.update': []
}
```

```vue
<!-- resources/js/Pages/Users/Index.vue -->
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

interface User {
  id: number
  name: string
  email: string
}

interface PaginatedData<T> {
  data: T[]
}

interface Filters {
  search: string
  role: string
}

interface Props {
  users: PaginatedData<User>
  filters: Filters
}

const props = defineProps<Props>()

function handleDelete(user: User) {
  if (confirm(`Delete ${user.name}?`)) {
    router.delete(route('users.destroy', { user: user.id }))
  }
}

function handleSearch(search: string) {
  router.get(
    route('users.index'),
    { ...props.filters, search },
    { preserveState: true }
  )
}
</script>

<template>
  <div>
    <div class="mb-4 flex justify-between">
      <input
        type="search"
        :value="props.filters.search"
        placeholder="Search users..."
        @input="(e) => handleSearch((e.target as HTMLInputElement).value)"
      />

      <Link
        :href="route('users.create')"
        class="rounded bg-blue-600 px-4 py-2 text-white"
      >
        Add User
      </Link>
    </div>

    <table class="w-full">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in props.users.data" :key="user.id">
          <td>
            <Link :href="route('users.show', { user: user.id })">
              {{ user.name }}
            </Link>
          </td>
          <td>{{ user.email }}</td>
          <td class="flex gap-2">
            <Link
              :href="route('users.edit', { user: user.id })"
              class="text-blue-600"
            >
              Edit
            </Link>
            <button class="text-red-600" @click="handleDelete(user)">
              Delete
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

```vue
<!-- Reusable NavLink with active state using Ziggy -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Props {
  routeName: string
  params?: Record<string, unknown>
  activePattern?: string
}

const props = defineProps<Props>()

const isActive = computed(() =>
  route().current(props.activePattern || props.routeName)
)

const href = computed(() => route(props.routeName, props.params))
</script>

<template>
  <Link
    :href="href"
    :class="[
      'px-4 py-2',
      isActive ? 'bg-blue-100 text-blue-800' : 'text-gray-600',
    ]"
  >
    <slot />
  </Link>
</template>
```

```vue
<!-- Usage -->
<NavLink route-name="users.index" active-pattern="users.*">Users</NavLink>
```

## Why

Using Ziggy for route generation provides:

1. **Single Source of Truth**: Routes defined once in Laravel, used everywhere
2. **Refactoring Safety**: Route name changes are caught at compile time
3. **Parameter Validation**: TypeScript ensures correct route parameters
4. **No Hardcoding**: URLs don't break when route patterns change
5. **Active Route Detection**: Easy highlighting of current navigation items
6. **Query Parameters**: Clean syntax for adding query strings
7. **IDE Support**: Autocomplete for route names and parameters
