---
section: page-components
priority: high
description: Reload only specific props with only/except options for better performance
keywords: [partial, reload, props, performance, only, except]
---

# Page Partial Reloads

Use partial reloads to refresh only specific props without reloading the entire page, improving performance and user experience.

## Bad Example

```vue
<!-- Anti-pattern: Full page reload for updating single data -->
<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps(['stats', 'notifications', 'recentActivity'])

function refreshAll() {
  // This reloads ALL props, even unchanged ones
  router.reload()
}
</script>

<!-- Anti-pattern: Making separate API calls -->
<script setup>
import { ref } from 'vue'

const notifications = ref([])

async function refreshNotifications() {
  const response = await fetch('/api/notifications')
  notifications.value = await response.json() // Mixing Inertia with manual state
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Dashboard.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

interface Stat {
  id: number
  label: string
  value: number
}

interface Notification {
  id: number
  message: string
  read_at: string | null
}

interface Activity {
  id: number
  description: string
}

interface Props {
  stats: Stat[]
  notifications: Notification[]
  recentActivity: Activity[]
}

const props = defineProps<Props>()
const isRefreshing = ref(false)

// Reload only notifications
function refreshNotifications() {
  router.reload({
    only: ['notifications'],
    onStart: () => (isRefreshing.value = true),
    onFinish: () => (isRefreshing.value = false),
  })
}

// Reload multiple specific props
function refreshDashboardData() {
  router.reload({
    only: ['stats', 'recentActivity'],
    preserveScroll: true,
  })
}

// Reload everything except heavy data
function refreshWithoutActivity() {
  router.reload({
    except: ['recentActivity'],
  })
}
</script>

<template>
  <div>
    <div class="flex gap-4">
      <button @click="refreshNotifications" :disabled="isRefreshing">
        {{ isRefreshing ? 'Refreshing...' : 'Refresh Notifications' }}
      </button>
      <button @click="refreshDashboardData">Refresh Stats</button>
    </div>

    <NotificationList
      :notifications="props.notifications"
      :is-loading="isRefreshing"
    />
    <StatsGrid :stats="props.stats" />
    <ActivityFeed :activities="props.recentActivity" />
  </div>
</template>
```

```php
// Laravel Controller with lazy props for optimization
// app/Http/Controllers/DashboardController.php
use Inertia\Inertia;

public function index()
{
    return Inertia::render('Dashboard', [
        'stats' => fn () => $this->getStats(),
        'notifications' => fn () => auth()->user()->unreadNotifications,
        'recentActivity' => Inertia::lazy(fn () => $this->getRecentActivity()),
    ]);
}
```

```vue
<!-- Polling with partial reloads -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'

const props = defineProps<{ notificationCount: number }>()
let interval: ReturnType<typeof setInterval>

onMounted(() => {
  interval = setInterval(() => {
    router.reload({ only: ['notificationCount'] })
  }, 30000) // Refresh every 30 seconds
})

onUnmounted(() => {
  clearInterval(interval)
})
</script>

<template>
  <span class="badge">{{ props.notificationCount }}</span>
</template>
```

## Why

Partial reloads are essential for building efficient Inertia applications:

1. **Performance**: Only transfer and process the data that actually changed
2. **Bandwidth**: Reduce network payload, especially important for mobile users
3. **Server Load**: Laravel only evaluates the requested props when using closures
4. **UX Continuity**: Other parts of the page remain stable during updates
5. **Real-time Features**: Enable efficient polling without full page refreshes
6. **Lazy Loading**: Combine with Inertia::lazy() for on-demand data loading
