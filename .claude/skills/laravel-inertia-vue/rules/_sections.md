# Rule Sections

## Priority Levels

| Level | Description | When to Apply |
|-------|-------------|---------------|
| CRITICAL | Essential for any Inertia app | Always |
| HIGH | Important for maintainability and UX | Most projects |
| MEDIUM | Performance and developer experience | Growing applications |
| LOW | Specialized patterns | Large-scale apps |

## Section Overview

### Page Components (CRITICAL)
Core patterns for building Inertia page components with Vue 3. Proper TypeScript typing via `defineProps`, Head management, layout assignment with `defineOptions({ layout })`, and props handling.

### Forms & Validation (CRITICAL)
Complete form handling with useForm composable. Validation errors, file uploads, progress tracking, dirty state, and data transformation using Vue 3 reactivity.

### Navigation (CRITICAL-HIGH)
Inertia's Link component and router for SPA-like navigation. Programmatic navigation, state preservation, history management, and external links.

### Shared Data (CRITICAL-HIGH)
Global props shared across all pages via HandleInertiaRequests middleware. Authentication data, flash messages, Ziggy routes, and app configuration accessed via `usePage()`.

### Layouts (CRITICAL)
Persistent layout patterns using `defineOptions({ layout })` that maintain state across navigation. Prevents unnecessary re-renders and provides better performance.

### Advanced Patterns (MEDIUM)
Partial reloads, scroll preservation, and optimization techniques for complex Inertia applications.
