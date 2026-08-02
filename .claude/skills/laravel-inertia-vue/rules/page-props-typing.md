---
section: page-components
priority: critical
description: Type-safe page props with TypeScript interfaces using defineProps in Vue 3
keywords: [typescript, props, typing, interfaces, type-safety, defineProps]
---

# Page Props Typing

All Inertia page props should be strongly typed using TypeScript interfaces passed to `defineProps<Props>()`, optionally extending a base PageProps type containing shared data.

## Bad Example

```vue
<!-- Anti-pattern: Using any or missing types -->
<script setup>
const props = defineProps(['users', 'filters']) // No type safety
</script>

<template>
  <div v-for="user in props.users" :key="user.id">{{ user.name }}</div>
</template>
```

```vue
<!-- Anti-pattern: Inline typing without base shared props -->
<script setup lang="ts">
const props = defineProps<{
  users: { id: number; name: string }[]
}>()
// Missing auth, flash, and other shared props
</script>
```

## Good Example

```ts
// resources/js/types/index.d.ts
export interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

export interface PaginatedData<T> {
  data: T[]
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number
    last_page: number
    path: string
    per_page: number
    to: number
    total: number
  }
}

export interface PageProps {
  auth: {
    user: User
  }
  flash: {
    success?: string
    error?: string
  }
  ziggy: {
    url: string
    port: number | null
    defaults: Record<string, unknown>
    routes: Record<string, unknown>
  }
}
```

```vue
<!-- resources/js/Pages/Users/Index.vue -->
<script setup lang="ts">
import type { PageProps, PaginatedData, User } from '@/types'

interface Filters {
  search: string
  role: string
  status: 'active' | 'inactive' | 'all'
}

interface Props extends PageProps {
  users: PaginatedData<User>
  filters: Filters
  roles: { value: string; label: string }[]
}

const props = defineProps<Props>()
</script>

<template>
  <div>
    <h1>Users ({{ props.users.meta.total }})</h1>
    <UserCard
      v-for="user in props.users.data"
      :key="user.id"
      :user="user"
    />
  </div>
</template>
```

## Why

Proper props typing is essential for Inertia applications:

1. **Contract Enforcement**: Types create a contract between Laravel controllers and Vue components
2. **IDE Support**: Autocomplete and inline documentation improve developer productivity
3. **Error Prevention**: Catch typos and missing properties before runtime
4. **Refactoring Safety**: TypeScript will flag all affected components when data shapes change
5. **Documentation**: Types serve as living documentation for the data flow
6. **Shared Data Access**: Extending PageProps ensures access to auth, flash messages, and Ziggy routes
