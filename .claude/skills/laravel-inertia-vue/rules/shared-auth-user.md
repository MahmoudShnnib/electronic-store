---
section: shared-data
priority: critical
description: Access authenticated user data through Inertia's shared props in Vue 3
keywords: [auth, user, authentication, shared, middleware, HandleInertiaRequests, usePage, vue3]
---

# Shared Auth User Data

Access authenticated user data through Inertia's shared props, set up via Laravel middleware, for consistent auth state across all pages.

## Bad Example

```vue
<!-- Anti-pattern: Fetching user data separately -->
<script setup>
import { ref, onMounted } from 'vue'

const user = ref(null)

onMounted(async () => {
  const res = await fetch('/api/user')
  user.value = await res.json()
})
</script>

<template>
  <div v-if="user">Welcome {{ user.name }}</div>
</template>
```

## Good Example

```php
// app/Http/Middleware/HandleInertiaRequests.php
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar_url' => $request->user()->avatar_url,
                    'email_verified_at' => $request->user()->email_verified_at,
                    'roles' => $request->user()->roles->pluck('name'),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
            ],
        ]);
    }
}
```

```ts
// resources/js/types/index.d.ts
export interface User {
  id: number
  name: string
  email: string
  avatar_url: string | null
  email_verified_at: string | null
  roles: string[]
  permissions: string[]
}

export interface PageProps {
  auth: {
    user: User | null
  }
  flash: {
    success?: string
    error?: string
  }
}
```

```ts
// resources/js/composables/useAuth.ts
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { PageProps } from '@/types'

export function useAuth() {
  const page = usePage<PageProps>()
  const auth = computed(() => page.props.auth)

  return {
    user: computed(() => auth.value.user),
    isAuthenticated: computed(() => !!auth.value.user),
    isGuest: computed(() => !auth.value.user),
  }
}

export function usePermissions() {
  const { user } = useAuth()

  return {
    hasRole: (role: string) => user.value?.roles.includes(role) ?? false,
    hasPermission: (permission: string) =>
      user.value?.permissions.includes(permission) ?? false,
    hasAnyRole: (roles: string[]) =>
      roles.some((role) => user.value?.roles.includes(role)) ?? false,
    hasAllRoles: (roles: string[]) =>
      roles.every((role) => user.value?.roles.includes(role)) ?? false,
  }
}
```

```vue
<!-- resources/js/Components/UserMenu.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useAuth } from '@/composables/useAuth'

const { user, isAuthenticated } = useAuth()
</script>

<template>
  <div v-if="!isAuthenticated" class="flex gap-4">
    <Link :href="route('login')">Log In</Link>
    <Link :href="route('register')">Sign Up</Link>
  </div>

  <div v-else class="flex items-center gap-4">
    <img
      v-if="user?.avatar_url"
      :src="user.avatar_url"
      :alt="user.name"
      class="h-8 w-8 rounded-full"
    />
    <div
      v-else
      class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200"
    >
      {{ user?.name.charAt(0).toUpperCase() }}
    </div>
    <span>{{ user?.name }}</span>
    <Link :href="route('profile.edit')">Settings</Link>
    <Link :href="route('logout')" method="post" as="button">Log Out</Link>
  </div>
</template>
```

```vue
<!-- resources/js/Components/Can.vue - Permission-based rendering -->
<script setup lang="ts">
import { usePermissions } from '@/composables/useAuth'
import { computed } from 'vue'

interface Props {
  permission?: string
  role?: string
}

const props = defineProps<Props>()
const { hasPermission, hasRole } = usePermissions()

const authorized = computed(() => {
  if (props.permission) return hasPermission(props.permission)
  if (props.role) return hasRole(props.role)
  return false
})
</script>

<template>
  <slot v-if="authorized" />
  <slot v-else name="fallback" />
</template>
```

```vue
<!-- Usage in pages -->
<script setup lang="ts">
import { useAuth, usePermissions } from '@/composables/useAuth'
import Can from '@/Components/Can.vue'

const { user } = useAuth()
const { hasRole } = usePermissions()
</script>

<template>
  <div>
    <h1>Welcome, {{ user?.name }}</h1>

    <!-- Role-based content -->
    <Link v-if="hasRole('admin')" :href="route('admin.dashboard')">
      Admin Panel
    </Link>

    <!-- Permission-based component -->
    <Can permission="manage-users">
      <Link :href="route('users.index')">Manage Users</Link>
    </Can>

    <!-- With fallback slot -->
    <Can permission="create-posts">
      <Link :href="route('posts.create')">Create Post</Link>
      <template #fallback>
        <p>You cannot create posts</p>
      </template>
    </Can>
  </div>
</template>
```

## Why

Sharing auth data through Inertia provides:

1. **Single Source of Truth**: User data is consistent across all components
2. **No Extra Requests**: Auth data comes with every Inertia response
3. **Type Safety**: TypeScript interfaces ensure correct user data shape
4. **Easy Access**: Custom composables make auth data accessible anywhere
5. **Permission Handling**: Role and permission checks can be centralized
6. **SSR Compatible**: Works correctly with server-side rendering
7. **Automatic Updates**: User data refreshes on every page visit
