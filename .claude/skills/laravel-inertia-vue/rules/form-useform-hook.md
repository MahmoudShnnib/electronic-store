---
section: forms
priority: critical
description: Complete form example using useForm with multiple input types — text, textarea, select, checkbox
keywords: [useForm, form, textarea, select, checkbox, multiple-inputs, vue3]
---

# Form useForm — Multiple Input Types

Use Inertia's useForm composable for all form handling to get automatic state management, validation error handling, and submission progress tracking.

## Bad Example

```vue
<!-- Anti-pattern: Manual state management -->
<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const title = ref('')
const content = ref('')
const errors = reactive({})
const processing = ref(false)

function handleSubmit() {
  processing.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  router.post('/posts', { title: title.value, content: content.value }, {
    onError: (errs) => Object.assign(errors, errs),
    onFinish: () => (processing.value = false),
  })
}
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <input v-model="title" />
    <span v-if="errors.title">{{ errors.title }}</span>
  </form>
</template>
```

## Good Example

```vue
<!-- resources/js/Pages/Posts/Create.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface CreatePostForm {
  title: string
  content: string
  category_id: number | ''
  published: boolean
  tags: string[]
}

const form = useForm<CreatePostForm>({
  title: '',
  content: '',
  category_id: '',
  published: false,
  tags: [],
})

function submit() {
  form.post(route('posts.store'), {
    onSuccess: () => form.reset(),
  })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6">
    <div>
      <label for="title" class="block text-sm font-medium">Title</label>
      <input
        id="title"
        v-model="form.title"
        type="text"
        class="mt-1 block w-full rounded-md border-gray-300"
      />
      <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">
        {{ form.errors.title }}
      </p>
    </div>

    <div>
      <label for="content" class="block text-sm font-medium">Content</label>
      <textarea
        id="content"
        v-model="form.content"
        class="mt-1 block w-full rounded-md border-gray-300"
        rows="6"
      />
      <p v-if="form.errors.content" class="mt-2 text-sm text-red-600">
        {{ form.errors.content }}
      </p>
    </div>

    <div>
      <label for="category" class="block text-sm font-medium">Category</label>
      <select
        id="category"
        v-model="form.category_id"
        class="mt-1 block w-full rounded-md border-gray-300"
      >
        <option value="">Select a category</option>
        <!-- Category options -->
      </select>
      <p v-if="form.errors.category_id" class="mt-2 text-sm text-red-600">
        {{ form.errors.category_id }}
      </p>
    </div>

    <div class="flex items-center gap-2">
      <input
        id="published"
        v-model="form.published"
        type="checkbox"
      />
      <label for="published" class="text-sm font-medium">
        Publish immediately
      </label>
    </div>

    <button
      type="submit"
      :disabled="form.processing"
      class="rounded-md bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
    >
      {{ form.processing ? 'Creating...' : 'Create Post' }}
    </button>
  </form>
</template>
```

## Why

The useForm composable provides significant advantages over manual form handling:

1. **Automatic State Management**: Single source of truth for all form data
2. **Built-in Error Handling**: Validation errors from Laravel automatically populate
3. **Processing State**: Track submission status for button states and UI feedback
4. **Type Safety**: Generic typing ensures form data matches expected shape
5. **Helper Methods**: reset, clearErrors, and transform simplify common operations
6. **Memory Management**: Proper cleanup prevents memory leaks
7. **Consistent API**: post, put, patch, delete methods handle HTTP verbs correctly
8. **Vue Reactivity**: Form fields are fully reactive — use `v-model` directly
