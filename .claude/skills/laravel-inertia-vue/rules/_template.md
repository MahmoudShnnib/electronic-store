---
section: [page-components|forms|navigation|shared-data|layouts]
priority: [critical|high|medium|low]
description: Brief one-line description of the rule
keywords: [relevant, keywords, for, searching]
---

# Rule Title

Brief explanation of what this rule covers and why it matters for Laravel + Inertia.js + Vue 3 development.

## Bad Example

```vue
<!-- Anti-pattern: What NOT to do -->
<!-- Explain why this approach is problematic -->
<script setup>
// bad code
</script>

<template>
  <!-- bad template -->
</template>
```

## Good Example

```vue
<!-- Correct approach: Best practice implementation -->
<!-- resources/js/Pages/Example.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

// Full working example with TypeScript
const form = useForm({ /* ... */ })
</script>

<template>
  <!-- Vue template -->
</template>
```

### Laravel Backend (if applicable)

```php
// app/Http/Controllers/ExampleController.php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ExampleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Example', [
            // Props
        ]);
    }
}
```

## Why

This pattern is important because:

1. **Reason 1**: Explanation of first benefit
2. **Reason 2**: Explanation of second benefit
3. **Reason 3**: Explanation of third benefit
4. **Performance**: Performance implications
5. **Type Safety**: TypeScript benefits
6. **Developer Experience**: DX improvements
7. **Maintainability**: Long-term maintenance benefits
