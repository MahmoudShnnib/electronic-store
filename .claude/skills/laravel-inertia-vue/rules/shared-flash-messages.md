---
section: shared-data
priority: high
description: Display Laravel session flash messages in Vue 3 components
keywords: [flash, messages, feedback, session, notifications, toast, vue3]
---

# Shared Flash Messages

Laravel's session flash messages need to be passed to Inertia and displayed in Vue 3. This enables consistent feedback to users after form submissions and actions.

## Laravel Setup

```php
// app/Http/Middleware/HandleInertiaRequests.php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ]);
    }
}
```

```php
// In controller
public function store(StorePostRequest $request): RedirectResponse
{
    Post::create($request->validated());

    return redirect()
        ->route('posts.index')
        ->with('success', 'Post created successfully!');
}

public function destroy(Post $post): RedirectResponse
{
    $post->delete();

    return redirect()
        ->route('posts.index')
        ->with('success', 'Post deleted successfully!');
}
```

## Vue 3 Implementation

### TypeScript Types

```ts
// resources/js/types/index.ts
export interface PageProps {
  auth: {
    user: {
      id: number
      name: string
      email: string
    } | null
  }
  flash: {
    success?: string
    error?: string
    warning?: string
    info?: string
  }
}
```

### Flash Message Component

```vue
<!-- resources/js/Components/FlashMessages.vue -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import type { PageProps } from '@/types'

const page = usePage<PageProps>()
const flash = computed(() => page.props.flash)
const messages = ref({ ...flash.value })
let timer: ReturnType<typeof setTimeout> | null = null

watch(
  flash,
  (newFlash) => {
    messages.value = { ...newFlash }

    // Auto-dismiss after 5 seconds
    if (newFlash.success || newFlash.error || newFlash.warning || newFlash.info) {
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => {
        messages.value = {}
      }, 5000)
    }
  },
  { deep: true }
)

function dismiss() {
  messages.value = {}
  if (timer) clearTimeout(timer)
}
</script>

<template>
  <div
    v-if="messages.success || messages.error || messages.warning || messages.info"
    class="fixed top-4 right-4 z-50 space-y-2"
  >
    <div
      v-if="messages.success"
      class="flex items-center gap-2 rounded border border-green-400 bg-green-100 px-4 py-3 text-green-700"
    >
      <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
          clip-rule="evenodd"
        />
      </svg>
      <span>{{ messages.success }}</span>
      <button @click="dismiss">×</button>
    </div>

    <div
      v-if="messages.error"
      class="flex items-center gap-2 rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700"
    >
      <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
          clip-rule="evenodd"
        />
      </svg>
      <span>{{ messages.error }}</span>
      <button @click="dismiss">×</button>
    </div>

    <div
      v-if="messages.warning"
      class="rounded border border-yellow-400 bg-yellow-100 px-4 py-3 text-yellow-700"
    >
      {{ messages.warning }}
    </div>

    <div
      v-if="messages.info"
      class="rounded border border-blue-400 bg-blue-100 px-4 py-3 text-blue-700"
    >
      {{ messages.info }}
    </div>
  </div>
</template>
```

### Add to Layout

```vue
<!-- resources/js/Layouts/AppLayout.vue -->
<script setup lang="ts">
import FlashMessages from '@/Components/FlashMessages.vue'
</script>

<template>
  <div>
    <FlashMessages />
    <nav><!-- ... --></nav>
    <main><slot /></main>
  </div>
</template>
```

### Using a Toast Library

```vue
<!-- With vue-sonner or similar -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { watch, computed } from 'vue'
import { toast } from 'vue-sonner'

const page = usePage()
const flash = computed(() => page.props.flash as { success?: string; error?: string })

watch(flash, (newFlash) => {
  if (newFlash.success) toast.success(newFlash.success)
  if (newFlash.error) toast.error(newFlash.error)
}, { deep: true })
</script>
```

## Benefits

- Consistent user feedback across all pages
- Works with Laravel's standard flash system
- Auto-dismisses after timeout
- Type-safe with TypeScript
- Vue 3 reactivity handles flash changes on navigation
