---
section: forms
priority: high
description: Reset form state after submissions using Inertia's reset helpers in Vue 3
keywords: [reset, clear, form, state, revert, cleanup, vue3]
---

# Form Reset Handling

Properly reset form state after successful submissions or when clearing user input using Inertia's reset helpers.

## Bad Example

```vue
<!-- Anti-pattern: Manual state reset -->
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({ name: '', email: '', message: '' })

function submit() {
  form.post('/contact', {
    onSuccess: () => {
      // Manually resetting each field - tedious and error-prone
      form.name = ''
      form.email = ''
      form.message = ''
    },
  })
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Contact.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

interface ContactForm {
  name: string
  email: string
  subject: string
  message: string
  attachment: File | null
}

const fileInputRef = ref<HTMLInputElement | null>(null)

const form = useForm<ContactForm>({
  name: '',
  email: '',
  subject: '',
  message: '',
  attachment: null,
})

function submit() {
  form.post(route('contact.store'), {
    preserveScroll: true,
    onSuccess: () => {
      // Reset all fields to initial values
      form.reset()

      // Clear file input (not controlled by Inertia)
      if (fileInputRef.value) {
        fileInputRef.value.value = ''
      }
    },
  })
}

// Reset specific fields only
function resetMessageFields() {
  form.reset('subject', 'message')
  form.clearErrors('subject', 'message')
}

// Clear form manually (user action)
function handleClear() {
  form.reset()
  form.clearErrors()
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6">
    <div>
      <label for="name">Name</label>
      <input id="name" v-model="form.name" />
    </div>

    <div>
      <label for="email">Email</label>
      <input id="email" v-model="form.email" type="email" />
    </div>

    <div>
      <label for="subject">Subject</label>
      <input id="subject" v-model="form.subject" />
    </div>

    <div>
      <label for="message">Message</label>
      <textarea id="message" v-model="form.message" rows="4" />
    </div>

    <div>
      <label for="attachment">Attachment</label>
      <input
        ref="fileInputRef"
        id="attachment"
        type="file"
        @change="(e) => form.attachment = (e.target as HTMLInputElement).files?.[0] || null"
      />
    </div>

    <div class="flex gap-4">
      <button
        type="submit"
        :disabled="form.processing"
        class="rounded bg-blue-600 px-4 py-2 text-white"
      >
        Send Message
      </button>

      <button
        type="button"
        :disabled="form.processing"
        class="rounded bg-gray-200 px-4 py-2 text-gray-700"
        @click="handleClear"
      >
        Clear Form
      </button>

      <button
        type="button"
        class="rounded bg-gray-200 px-4 py-2 text-gray-700"
        @click="resetMessageFields"
      >
        Clear Message Only
      </button>
    </div>
  </form>
</template>
```

```vue
<!-- Edit form with reset to original values -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface Post {
  id: number
  title: string
  content: string
  published: boolean
}

interface Props {
  post: Post
}

const props = defineProps<Props>()

const form = useForm({
  title: props.post.title,
  content: props.post.content,
  published: props.post.published,
})

function submit() {
  form.put(route('posts.update', props.post.id))
}

// Reset to original prop values
function handleRevert() {
  form.reset()
}
</script>

<template>
  <form @submit.prevent="submit">
    <!-- Form fields -->

    <div class="flex items-center gap-4">
      <button type="submit" :disabled="form.processing || !form.isDirty">
        Save Changes
      </button>

      <button
        v-if="form.isDirty"
        type="button"
        class="text-gray-600 hover:text-gray-800"
        @click="handleRevert"
      >
        Discard Changes
      </button>
    </div>

    <p v-if="form.isDirty" class="mt-2 text-sm text-amber-600">
      You have unsaved changes
    </p>
  </form>
</template>
```

## Why

Proper reset handling ensures a smooth user experience:

1. **Clean State**: Forms return to a known initial state after submission
2. **Selective Reset**: Reset specific fields while preserving others
3. **Error Clearing**: clearErrors works alongside reset for complete cleanup
4. **File Inputs**: Remember to clear uncontrolled file inputs separately
5. **Dirty Tracking**: isDirty flag helps show unsaved changes warnings
6. **Revert Capability**: Let users undo their changes on edit forms
7. **Performance**: No page reload needed to reset form state
