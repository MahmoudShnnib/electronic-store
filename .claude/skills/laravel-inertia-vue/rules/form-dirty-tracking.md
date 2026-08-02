---
section: forms
priority: high
description: Track unsaved changes with isDirty and warn before navigation in Vue 3
keywords: [isDirty, unsaved, changes, navigation, warning, beforeunload, vue3]
---

# Form Dirty Tracking

Use Inertia's isDirty flag to track unsaved changes and warn users before navigating away from modified forms.

## Bad Example

```vue
<!-- Anti-pattern: Not tracking form changes -->
<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps(['user'])
const form = useForm({ name: props.user.name, email: props.user.email })

// Users can navigate away and lose all changes without warning
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Profile/Edit.vue -->
<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { onMounted, onUnmounted, watch } from 'vue'

interface User {
  name: string
  email: string
  bio: string
}

interface Props {
  user: User
}

const props = defineProps<Props>()

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  bio: props.user.bio || '',
})

// Warn before browser close/refresh
function handleBeforeUnload(e: BeforeUnloadEvent) {
  if (form.isDirty) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onMounted(() => {
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onUnmounted(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})

// Intercept Inertia navigation
const removeListener = router.on('before', (event) => {
  if (form.isDirty && !confirm('You have unsaved changes. Are you sure you want to leave?')) {
    event.preventDefault()
  }
})

onUnmounted(() => {
  removeListener()
})

function submit() {
  form.put(route('profile.update'))
}
</script>

<template>
  <div>
    <!-- Unsaved changes indicator -->
    <div
      v-if="form.isDirty"
      class="mb-4 flex items-center justify-between rounded-md bg-amber-50 px-4 py-2 text-amber-800"
    >
      <span>You have unsaved changes</span>
      <button
        type="button"
        class="text-sm underline hover:no-underline"
        @click="form.reset()"
      >
        Discard changes
      </button>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input
          id="name"
          v-model="form.name"
          class="mt-1 block w-full rounded-md border-gray-300"
        />
      </div>

      <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="mt-1 block w-full rounded-md border-gray-300"
        />
      </div>

      <div>
        <label for="bio" class="block text-sm font-medium">Bio</label>
        <textarea
          id="bio"
          v-model="form.bio"
          rows="4"
          class="mt-1 block w-full rounded-md border-gray-300"
        />
      </div>

      <div class="flex items-center gap-4">
        <button
          type="submit"
          :disabled="form.processing || !form.isDirty"
          class="rounded-md bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
        >
          {{ form.processing ? 'Saving...' : 'Save Changes' }}
        </button>

        <span class="text-sm text-gray-500">
          {{ form.isDirty ? 'Unsaved changes' : 'All changes saved' }}
        </span>
      </div>
    </form>
  </div>
</template>
```

```ts
// Reusable composable for forms with navigation blocking
// resources/js/composables/useFormWithNavBlock.ts
import { useForm, router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'

export function useFormWithNavBlock<T extends Record<string, unknown>>(initialData: T) {
  const form = useForm<T>(initialData)

  function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (form.isDirty) {
      e.preventDefault()
      e.returnValue = ''
    }
  }

  onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
  })

  onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
  })

  const removeListener = router.on('before', (event) => {
    if (form.isDirty && !confirm('Discard unsaved changes?')) {
      event.preventDefault()
    }
  })

  onUnmounted(() => {
    removeListener()
  })

  return form
}
```

```vue
<!-- Usage of the composable -->
<script setup lang="ts">
import { useFormWithNavBlock } from '@/composables/useFormWithNavBlock'

const props = defineProps<{ post: { title: string; content: string } }>()

const form = useFormWithNavBlock({
  title: props.post.title,
  content: props.post.content,
})

// Navigation blocking is automatic
</script>
```

## Why

Dirty tracking prevents accidental data loss:

1. **User Protection**: Warn users before they lose unsaved work
2. **Built-in Support**: isDirty is provided by useForm automatically
3. **Browser Events**: Handle both page refresh and navigation away
4. **Inertia Navigation**: Intercept SPA navigation with router.on('before')
5. **Visual Feedback**: Show users when they have unsaved changes
6. **Button States**: Disable submit button when no changes exist
7. **Better UX**: Users trust the form won't lose their data
