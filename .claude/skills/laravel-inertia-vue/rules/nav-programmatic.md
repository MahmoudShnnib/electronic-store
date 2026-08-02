---
section: navigation
priority: critical
description: Use router for programmatic navigation and form submissions in Vue 3
keywords: [router, programmatic, navigation, visit, get, post, put, delete, vue3]
---

# Programmatic Navigation

Use Inertia's router for programmatic navigation, redirects, and form submissions outside of Link components.

## Bad Example

```vue
<!-- Anti-pattern: Using window.location -->
<script setup>
function handleClick() {
  window.location.href = '/dashboard'
}

function goBack() {
  window.history.back()
}
</script>

<!-- Anti-pattern: Using fetch for navigation -->
<script setup>
async function handleSubmit() {
  const response = await fetch('/api/users', {
    method: 'POST',
    body: JSON.stringify(data),
  })
  if (response.ok) {
    window.location.href = '/users'
  }
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Users/Create.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

interface UserData {
  name: string
  email: string
}

function handleCancel() {
  router.visit(route('users.index'))
}

function handleCreate(userData: UserData) {
  router.post(route('users.store'), userData, {
    onSuccess: () => {
      // Redirect happens automatically from Laravel
    },
    onError: (errors) => {
      console.error('Validation failed:', errors)
    },
  })
}
</script>

<template>
  <div>
    <UserForm @submit="handleCreate" />
    <button @click="handleCancel">Cancel</button>
  </div>
</template>
```

```vue
<!-- Advanced router usage examples -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// GET request with query parameters
function searchUsers(query: string) {
  router.get(route('users.index'), { search: query }, {
    preserveState: true,
    preserveScroll: true,
  })
}

// POST request
function createPost(data: { title: string; body: string }) {
  router.post(route('posts.store'), data, {
    onSuccess: (page) => {
      console.log('Post created!', page.props.flash)
    },
  })
}

// PUT request for updates
function updateUser(userId: number, data: { name: string; email: string }) {
  router.put(route('users.update', userId), data, {
    preserveScroll: true,
  })
}

// PATCH for partial updates
function togglePublished(postId: number) {
  router.patch(route('posts.toggle-published', postId))
}

// DELETE request
function deleteUser(userId: number) {
  if (confirm('Are you sure you want to delete this user?')) {
    router.delete(route('users.destroy', userId))
  }
}

// Reload current page
function refreshData() {
  router.reload()
}

// Partial reload - only fetch specific props
function refreshNotifications() {
  router.reload({ only: ['notifications'] })
}

// Navigate with all options
function advancedNavigation() {
  router.visit(route('dashboard'), {
    method: 'get',
    data: { tab: 'analytics' },
    replace: true,
    preserveState: true,
    preserveScroll: true,
    only: ['stats'],
    headers: { 'X-Custom': 'value' },
    onBefore: () => confirm('Navigate away?'),
    onStart: () => console.log('Navigation started'),
    onProgress: (progress) => console.log(`${progress.percentage}% loaded`),
    onSuccess: (page) => console.log('Navigation successful', page),
    onError: (errors) => console.error('Errors:', errors),
    onFinish: () => console.log('Navigation finished'),
  })
}
</script>
```

```vue
<!-- Product actions using router -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

interface Product {
  id: number
  name: string
}

interface Props {
  product: Product
}

const props = defineProps<Props>()

function handleDuplicate() {
  router.post(route('products.duplicate', props.product.id))
}

function handleArchive() {
  router.patch(route('products.archive', props.product.id), {}, {
    preserveScroll: true,
  })
}

function handleExport() {
  // For file downloads, use regular window.location
  window.location.href = route('products.export', props.product.id)
}
</script>

<template>
  <div class="flex gap-2">
    <button @click="handleDuplicate">Duplicate</button>
    <button @click="handleArchive">Archive</button>
    <button @click="handleExport">Export PDF</button>
  </div>
</template>
```

## Why

Programmatic navigation with Inertia's router provides:

1. **Consistent Behavior**: Same SPA navigation as Link components
2. **Full Control**: Access to all visit options and lifecycle callbacks
3. **HTTP Methods**: Support for GET, POST, PUT, PATCH, DELETE
4. **Event Handling**: Navigate from button clicks, form submissions, etc.
5. **Conditional Logic**: Navigate based on validation or user confirmation
6. **Progress Tracking**: Same loading indicator as Link navigation
7. **State Management**: Preserve scroll, state, and partial reload support
8. **Error Handling**: Proper callback structure for success and error states
