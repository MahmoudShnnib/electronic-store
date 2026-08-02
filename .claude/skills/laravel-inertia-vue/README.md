# Laravel + Inertia.js + Vue 3

Patterns for building modern monolithic applications with Laravel, Inertia.js, and Vue 3.

## Overview

This skill provides guidance for:
- Page component structure and typing with Vue 3 Composition API
- Form handling with useForm composable
- Navigation and partial reloads
- Shared data and authentication
- Persistent layouts
- File uploads

## Categories

### 1. Page Components (Critical)
Structure, typing, and best practices for Inertia pages with `<script setup>`.

### 2. Forms & Validation (Critical)
useForm composable, error handling, and form state management with `v-model`.

### 3. Navigation & Links (High)
Link component, preserve state, and partial reloads.

### 4. Shared Data (High)
Authentication, flash messages, and global props via `usePage()`.

### 5. Layouts (Medium)
Persistent layouts using `defineOptions({ layout })` for better UX and performance.

### 6. File Uploads (Medium)
Handling file uploads with progress tracking.

## Quick Start

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  title: '',
  body: '',
})

function submit() {
  form.post(route('posts.store'))
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.title" />
    <span v-if="form.errors.title">{{ form.errors.title }}</span>
    <button :disabled="form.processing">Submit</button>
  </form>
</template>
```

## Usage

This skill triggers automatically when:
- Building Inertia.js pages with Vue 3
- Handling forms with useForm composable
- Managing shared data via usePage
- Implementing layouts

## References

- [Inertia.js Documentation](https://inertiajs.com/)
- [Laravel Documentation](https://laravel.com/docs)
- [Vue 3 Documentation](https://vuejs.org/)
