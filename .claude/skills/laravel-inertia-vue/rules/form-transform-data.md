---
section: forms
priority: medium
description: Transform form data before submission using the transform method in Vue 3
keywords: [transform, data, conversion, formatting, preprocessing, vue3]
---

# Form Transform Data

Use the transform method to modify form data before submission without changing the original form state.

## Bad Example

```vue
<!-- Anti-pattern: Modifying data directly before submit -->
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  price: '', // Stored as string for input
  tags: [],  // Array that needs to be comma-separated
})

function submit() {
  // Mutating form data directly causes issues
  form.price = parseFloat(form.price) * 100 // Changes the UI too!
  form.post('/products') // May not send transformed data as expected
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Products/Create.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface ProductForm {
  name: string
  price: string       // String for input control
  discount_percent: string
  tags: string[]
  publish_date: string
  is_featured: boolean
}

const form = useForm<ProductForm>({
  name: '',
  price: '',
  discount_percent: '',
  tags: [],
  publish_date: '',
  is_featured: false,
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      // Convert price to cents for backend
      price: Math.round(parseFloat(data.price) * 100),
      // Convert percentage string to decimal
      discount_percent: data.discount_percent
        ? parseFloat(data.discount_percent) / 100
        : null,
      // Format date for Laravel
      publish_date: data.publish_date || null,
      // Convert tags array to comma-separated string
      tags: data.tags.join(','),
    }))
    .post(route('products.store'))
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6">
    <div>
      <label for="name">Product Name</label>
      <input id="name" v-model="form.name" />
    </div>

    <div>
      <label for="price">Price ($)</label>
      <input
        id="price"
        v-model="form.price"
        type="number"
        step="0.01"
        min="0"
        placeholder="29.99"
      />
      <!-- User sees dollars, backend receives cents -->
    </div>

    <div>
      <label for="discount">Discount (%)</label>
      <input
        id="discount"
        v-model="form.discount_percent"
        type="number"
        min="0"
        max="100"
        placeholder="10"
      />
      <!-- User enters 10, backend receives 0.10 -->
    </div>

    <TagInput :tags="form.tags" @update:tags="form.tags = $event" />

    <button type="submit" :disabled="form.processing">Create Product</button>
  </form>
</template>
```

```vue
<!-- More complex transformation example -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

interface RegistrationForm {
  first_name: string
  last_name: string
  email: string
  phone: string
  address: {
    street: string
    city: string
    zip: string
  }
}

const form = useForm<RegistrationForm>({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  address: {
    street: '',
    city: '',
    zip: '',
  },
})

function submit() {
  form
    .transform((data) => ({
      // Combine names for backend
      name: `${data.first_name} ${data.last_name}`.trim(),
      email: data.email.toLowerCase().trim(),
      // Normalize phone number
      phone: data.phone.replace(/\D/g, ''),
      // Flatten nested address for backend
      street: data.address.street,
      city: data.address.city,
      zip: data.address.zip,
    }))
    .post(route('register'))
}
</script>

<template>
  <form @submit.prevent="submit">
    <!-- Form fields use the structured data shape -->
    <input v-model="form.first_name" placeholder="First Name" />
    <input v-model="form.last_name" placeholder="Last Name" />
    <input v-model="form.address.street" placeholder="Street Address" />
    <!-- ... -->
  </form>
</template>
```

## Why

The transform method provides clean data transformation:

1. **Separation of Concerns**: UI-friendly data shapes vs backend-required formats
2. **Immutability**: Original form data remains unchanged for continued editing
3. **Type Flexibility**: Input types (string) can differ from submission types (number)
4. **Validation Compatibility**: Errors still map to original field names
5. **Clean Components**: No manual data conversion scattered throughout submit handlers
6. **Reusability**: Same form can submit to different endpoints with different transformations
7. **Testability**: Transform logic is isolated and easy to test
