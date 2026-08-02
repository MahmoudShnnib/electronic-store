---
section: forms
priority: critical
description: Display Laravel validation errors with proper UX and accessibility patterns in Vue 3
keywords: [validation, errors, laravel, form, accessibility, ux, vue3]
---

# Form Validation Errors

Display Laravel validation errors using Inertia's built-in error handling with proper UX patterns for inline feedback.

## Bad Example

```vue
<!-- Anti-pattern: Not handling errors properly -->
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({ email: '', message: '' })
</script>

<template>
  <form @submit.prevent="form.post('/contact')">
    <input v-model="form.email" type="email" />
    <!-- No error display -->
    <textarea v-model="form.message" />
    <button type="submit">Send</button>
  </form>
</template>
```

## Good Example

```vue
<!-- resources/js/Components/InputError.vue -->
<script setup lang="ts">
interface Props {
  message?: string
  className?: string
}

const props = withDefaults(defineProps<Props>(), {
  className: '',
})
</script>

<template>
  <p v-if="props.message" :class="`text-sm text-red-600 ${props.className}`">
    {{ props.message }}
  </p>
</template>
```

```vue
<!-- resources/js/Pages/Contact.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { nextTick } from 'vue'
import InputError from '@/Components/InputError.vue'

interface ContactForm {
  name: string
  email: string
  subject: string
  message: string
}

const form = useForm<ContactForm>({
  name: '',
  email: '',
  subject: '',
  message: '',
})

function submit() {
  form.post(route('contact.store'), {
    onError: () => {
      // Focus first field with error
      nextTick(() => {
        const firstError = Object.keys(form.errors)[0]
        if (firstError) {
          document.getElementById(firstError)?.focus()
        }
      })
    },
  })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6" novalidate>
    <!-- Show summary of errors at top for accessibility -->
    <div
      v-if="form.hasErrors"
      class="rounded-md bg-red-50 p-4"
      role="alert"
      aria-labelledby="error-heading"
    >
      <h3 id="error-heading" class="text-sm font-medium text-red-800">
        There were {{ Object.keys(form.errors).length }} errors with your submission
      </h3>
      <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
        <li v-for="(message, field) in form.errors" :key="field">
          {{ message }}
        </li>
      </ul>
    </div>

    <div>
      <label for="name" class="block text-sm font-medium text-gray-700">
        Name
      </label>
      <input
        id="name"
        v-model="form.name"
        type="text"
        :aria-invalid="!!form.errors.name"
        :aria-describedby="form.errors.name ? 'name-error' : undefined"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm',
          form.errors.name
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
        ]"
        @focus="form.clearErrors('name')"
      />
      <InputError id="name-error" :message="form.errors.name" class-name="mt-2" />
    </div>

    <div>
      <label for="email" class="block text-sm font-medium text-gray-700">
        Email
      </label>
      <input
        id="email"
        v-model="form.email"
        type="email"
        :aria-invalid="!!form.errors.email"
        :aria-describedby="form.errors.email ? 'email-error' : undefined"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm',
          form.errors.email
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
        ]"
        @focus="form.clearErrors('email')"
      />
      <InputError id="email-error" :message="form.errors.email" class-name="mt-2" />
    </div>

    <div>
      <label for="message" class="block text-sm font-medium text-gray-700">
        Message
      </label>
      <textarea
        id="message"
        v-model="form.message"
        :aria-invalid="!!form.errors.message"
        :aria-describedby="form.errors.message ? 'message-error' : undefined"
        rows="4"
        :class="[
          'mt-1 block w-full rounded-md shadow-sm',
          form.errors.message
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
        ]"
        @focus="form.clearErrors('message')"
      />
      <InputError id="message-error" :message="form.errors.message" class-name="mt-2" />
    </div>

    <button
      type="submit"
      :disabled="form.processing"
      class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
    >
      {{ form.processing ? 'Sending...' : 'Send Message' }}
    </button>
  </form>
</template>
```

## Why

Proper error handling improves both UX and accessibility:

1. **Immediate Feedback**: Users see exactly which fields need correction
2. **Accessibility**: ARIA attributes help screen readers announce errors
3. **Error Summary**: Top-level summary helps users understand total issues
4. **Visual Indicators**: Red borders and text clearly mark problematic fields
5. **Focus Management**: Focusing the first error field guides user attention
6. **Clear on Focus**: Removing errors when focusing encourages retry
7. **Laravel Integration**: Errors automatically map from Laravel validation responses
