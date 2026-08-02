---
section: forms
priority: critical
description: Use useForm composable for form state management and Laravel validation integration in Vue 3
keywords: [useForm, form, validation, laravel, state, composable, vue3]
---

# Form useForm Composable

The useForm composable is Inertia's primary way to handle forms in Vue 3. It provides automatic form state management, error handling, processing state, and seamless integration with Laravel validation.

## Incorrect

```vue
<!-- ❌ Manual state management -->
<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const title = ref('')
const body = ref('')
const errors = reactive({})
const processing = ref(false)

async function handleSubmit() {
  processing.value = true
  router.post('/posts', { title: title.value, body: body.value }, {
    onError: (errs) => Object.assign(errors, errs),
    onFinish: () => (processing.value = false),
  })
}
</script>
```

## Correct

```vue
<!-- ✅ Using useForm composable -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface FormData {
  title: string
  body: string
  category_id: string
}

const form = useForm<FormData>({
  title: '',
  body: '',
  category_id: '',
})

function handleSubmit() {
  form.post(route('posts.store'), {
    onSuccess: () => {
      form.reset() // Clear form on success
    },
  })
}
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <div>
      <label for="title">Title</label>
      <input
        id="title"
        v-model="form.title"
        type="text"
        @focus="form.clearErrors('title')"
      />
      <span v-if="form.errors.title" class="text-red-500">
        {{ form.errors.title }}
      </span>
    </div>

    <div>
      <label for="body">Body</label>
      <textarea
        id="body"
        v-model="form.body"
        @focus="form.clearErrors('body')"
      />
      <span v-if="form.errors.body" class="text-red-500">
        {{ form.errors.body }}
      </span>
    </div>

    <button type="submit" :disabled="form.processing">
      {{ form.processing ? 'Creating...' : 'Create Post' }}
    </button>
  </form>
</template>
```

## useForm Properties & Methods

```ts
const form = useForm({
  // Initial values
})

// Properties
form.data()         // Returns current form data as plain object
form.isDirty        // Has form been modified?
form.errors         // Validation errors from Laravel
form.hasErrors      // Are there any errors?
form.processing     // Is form submitting?
form.progress       // Upload progress (for file uploads)
form.wasSuccessful  // Was last submit successful?
form.recentlySuccessful // Was recently successful?

// Methods
form.reset()           // Reset all fields to initial values
form.reset('field')    // Reset specific field(s)
form.clearErrors()     // Clear all errors
form.clearErrors('field') // Clear specific error(s)
form.setError('field', 'message') // Set a custom error
form.transform((data) => ({ ...data })) // Transform before submit

// HTTP Methods
form.get(url, options)
form.post(url, options)
form.put(url, options)
form.patch(url, options)
form.delete(url, options)
```

## Setting Data

```ts
// Direct property assignment (Vue 3 reactive)
form.title = 'New Title'
form.category_id = '1'

// Multiple fields at once
Object.assign(form, { title: 'New Title', body: 'New Body' })
```

## HTTP Methods

```ts
// POST - Create
form.post(route('posts.store'))

// PUT - Full update
form.put(route('posts.update', post.id))

// PATCH - Partial update
form.patch(route('posts.update', post.id))

// DELETE
form.delete(route('posts.destroy', post.id), {
  onBefore: () => confirm('Are you sure?'),
})
```

## Options

```ts
form.post(route('posts.store'), {
  preserveState: true,
  preserveScroll: true,
  replace: true,
  onBefore: (visit) => { /* Return false to cancel */ },
  onStart: (visit) => {},
  onProgress: (progress) => {
    console.log(progress.percentage) // For file uploads
  },
  onSuccess: (page) => {
    form.reset()
  },
  onError: (errors) => {
    // Handle errors
  },
  onFinish: () => {
    // Always called (success or error)
  },
})
```

## Edit Form Pattern

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface Post {
  id: number
  title: string
  body: string
}

interface Props {
  post: Post
}

const props = defineProps<Props>()

// Initialize with existing data
const form = useForm({
  title: props.post.title,
  body: props.post.body,
})

function handleSubmit() {
  form.put(route('posts.update', props.post.id))
}
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <input v-model="form.title" />
    <textarea v-model="form.body" />
    <button :disabled="form.processing">
      {{ form.processing ? 'Saving...' : 'Save Changes' }}
    </button>
  </form>
</template>
```

## Benefits

- Automatic error handling from Laravel
- Processing state management
- Form reset functionality
- TypeScript support with generics
- Seamless Laravel integration
- Vue 3 reactive properties
