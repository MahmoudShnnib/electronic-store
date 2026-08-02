# Laravel + Inertia.js + Vue 3 Skill for AI Agents

This document provides guidance for AI agents on how to effectively use the Laravel + Inertia.js + Vue 3 skill patterns.

## Overview

This skill provides comprehensive patterns for building modern monolithic applications with Laravel backend, Inertia.js adapter, and Vue 3 frontend. It covers 24 rules across 6 key categories.

## When to Apply This Skill

Apply this skill when:

- Building Laravel applications with Inertia.js and Vue 3
- Creating or modifying Inertia page components with `<script setup>`
- Implementing forms with the useForm composable
- Setting up navigation with Inertia's Link component
- Configuring shared data through HandleInertiaRequests
- Implementing persistent layouts with `defineOptions({ layout })`
- Working with TypeScript in Inertia Vue apps
- Handling file uploads, validation, or flash messages

## Skill Categories

### 1. Page Components (CRITICAL)
**Priority**: CRITICAL | **Rules**: 6

Core patterns for structuring Inertia page components:
- Component structure with `<script setup lang="ts">`
- Props typing using `defineProps<Props>()`
- Head management for SEO and meta tags
- Layout assignment using `defineOptions({ layout })`
- Scroll preservation during navigation
- Partial reloads for performance

**When to reference**: Creating new pages, typing props, managing document head, or optimizing page loads.

### 2. Forms & Validation (CRITICAL)
**Priority**: CRITICAL | **Rules**: 8

Complete form handling with Inertia's useForm composable:
- Basic useForm setup and methods
- Displaying Laravel validation errors inline
- File uploads with progress tracking
- Form state management (isDirty, processing, errors)
- Data transformation before submission
- Reset and cleanup patterns

**When to reference**: Building forms, handling validation, file uploads, or managing form state.

### 3. Navigation (CRITICAL-HIGH)
**Priority**: CRITICAL to HIGH | **Rules**: 5

SPA-like navigation without full page reloads:
- Link component for internal navigation
- Programmatic navigation with router
- External links and download handling
- State preservation during navigation
- History management with replace option

**When to reference**: Implementing navigation, links, routing, or programmatic page transitions.

### 4. Shared Data (CRITICAL-HIGH)
**Priority**: CRITICAL to HIGH | **Rules**: 4

Global props shared across all pages:
- Authentication user data via `usePage()`
- Flash messages from Laravel
- Ziggy routes for type-safe routing
- App configuration and feature flags

**When to reference**: Accessing user data, displaying flash messages, using Laravel routes in JS, or sharing global config.

### 5. Layouts (CRITICAL)
**Priority**: CRITICAL | **Rules**: 1

Persistent layout implementation:
- `defineOptions({ layout })` pattern
- Nested layouts
- State preservation across navigation
- Performance benefits

**When to reference**: Setting up layouts, preventing layout re-renders, or optimizing navigation performance.

### 6. Advanced Patterns (MEDIUM)
**Priority**: MEDIUM | **Rules**: Covered in other sections

Advanced techniques integrated into other categories:
- Partial reloads (Page Components)
- Scroll preservation (Page Components)
- Progress indicators (Forms)
- Dirty tracking (Forms)

## Integration Patterns

### Laravel Controller → Inertia Page

```php
// Laravel Controller
public function index(): Response
{
    return Inertia::render('Users/Index', [
        'users' => User::paginate(10),
        'filters' => request()->only('search', 'role'),
    ]);
}
```

```vue
<!-- Vue Page Component -->
<script setup lang="ts">
interface Props {
  users: PaginatedData<User>
  filters: { search: string; role: string }
}

const props = defineProps<Props>()
</script>
```

### HandleInertiaRequests → Shared Props

```php
// Laravel Middleware
public function share(Request $request): array
{
    return [
        'auth' => ['user' => $request->user()],
        'flash' => ['success' => session('success')],
    ];
}
```

```vue
<!-- Vue Usage -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const auth = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)
</script>
```

### Form Submission → Laravel Validation

```vue
<!-- Vue Form -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({ name: '', email: '' })

function submit() {
  form.post(route('users.store'))
}
</script>
```

```php
// Laravel Controller
public function store(StoreUserRequest $request)
{
    User::create($request->validated());
    return redirect()->route('users.index')
        ->with('success', 'User created!');
}
```

## Common Patterns to Recommend

### 1. Type-Safe Page Components

Always use `defineProps` with TypeScript interfaces:

```vue
<script setup lang="ts">
interface Props {
  users: User[]
  stats: Stats
}

const props = defineProps<Props>()
</script>
```

### 2. Form Handling with useForm

Use the useForm composable for all forms:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({ /* initial values */ })
</script>
```

### 3. Navigation with Link

Use Link for internal navigation:

```vue
<template>
  <Link :href="route('users.show', user.id)">View User</Link>
</template>
```

### 4. Programmatic Navigation

Use router for programmatic navigation:

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

function createUser(data) {
  router.post(route('users.store'), data, {
    onSuccess: () => form.reset(),
  })
}
</script>
```

### 5. Persistent Layouts

Assign layouts using `defineOptions`:

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })
</script>
```

## Best Practices for AI Agents

1. **Always Type Props**: Use `defineProps<Props>()` with TypeScript interfaces
2. **Use route() Helper**: Never hardcode URLs, always use Ziggy's route() function
3. **Handle Errors**: Display validation errors inline with `v-if="form.errors.field"`
4. **Use v-model**: Bind form fields with `v-model="form.field"` for two-way binding
5. **Preserve State**: Use preserveState for filters and preserveScroll for pagination
6. **Lazy Load**: Use Inertia::lazy() for expensive props on Laravel side
7. **Flash Messages**: Set up flash message handling in layouts
8. **File Uploads**: Use progress tracking for file uploads
9. **External Links**: Use regular `<a>` tags, not Link component
10. **Replace History**: Use replace: true for filters and search

## Rule File Structure

Each rule file follows this pattern:

```markdown
---
section: [page-components|forms|navigation|shared-data|layouts]
priority: [critical|high|medium|low]
description: [one-line description]
keywords: [relevant, keywords]
---

# Rule Title

Explanation

## Bad Example
(anti-patterns)

## Good Example
(best practices with Vue 3 and Laravel)

## Why
(benefits and reasoning)
```

## Quick Reference

| Task | Reference Rules |
|------|----------------|
| Create page component | page-component-structure, page-props-typing |
| Add form | form-useform-basic, form-validation-errors |
| Handle file upload | form-file-uploads, form-progress-indicator |
| Set up navigation | nav-link-component, nav-programmatic |
| Display flash messages | shared-flash-messages |
| Access current user | shared-auth-user |
| Use Laravel routes | shared-ziggy-routes |
| Create layout | layout-persistent |
| Partial reload | page-partial-reloads |
| Preserve scroll | page-scroll-preservation |

## Tech Stack Requirements

- **PHP**: >= 8.3
- **Laravel**: >= 12.0
- **inertiajs/inertia-laravel**: >= 2.0
- **@inertiajs/vue3**: >= 2.0
- **Vue**: >= 3.3
- **TypeScript**: >= 5.0

## Official Documentation

- [Inertia.js](https://inertiajs.com/) - Core concepts and API
- [Laravel](https://laravel.com/docs) - Backend framework
- [Vue 3](https://vuejs.org/) - Frontend framework
- [Ziggy](https://github.com/tighten/ziggy) - Laravel routes in JavaScript

## Support

For issues or questions about this skill:
- Review the rule files in the `rules/` directory
- Check the examples in SKILL.md
- Refer to official documentation links above

---

**Version**: 1.0.0
**Last Updated**: 2026-03-17
**Maintainer**: Jasim k
