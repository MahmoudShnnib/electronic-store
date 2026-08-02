---
section: navigation
priority: high
description: Maintain component state during navigation with preserveState option in Vue 3
keywords: [preserveState, state, navigation, filters, tabs, ui-state, vue3]
---

# Navigation Preserve State

Use preserveState to maintain local component state during navigation, useful for tabs, filters, and accordion states.

## Bad Example

```vue
<!-- Anti-pattern: Losing local state on navigation -->
<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps(['users', 'filters'])
const expandedRows = ref([])
const viewMode = ref('list')
</script>

<template>
  <div>
    <!-- These links will reset expandedRows and viewMode -->
    <Link :href="route('users.index', { status: 'active' })">Active</Link>
    <Link :href="route('users.index', { status: 'inactive' })">Inactive</Link>

    <!-- User list that loses expanded state when filtering -->
    <UserRow
      v-for="user in props.users"
      :key="user.id"
      :user="user"
      :expanded="expandedRows.includes(user.id)"
    />
  </div>
</template>
```

## Good Example

```vue
<!-- resources/js/Pages/Users/Index.vue -->
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

interface User {
  id: number
  name: string
  email: string
}

interface PaginatedData<T> {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
}

interface Filters {
  status: string
  search: string
}

interface Props {
  users: PaginatedData<User>
  filters: Filters
}

const props = defineProps<Props>()

// Local UI state that should persist during navigation
const expandedRows = ref<number[]>([])
const viewMode = ref<'grid' | 'list'>('list')
const selectedIds = ref<number[]>([])

// Filter navigation with state preservation
function filterByStatus(status: string) {
  router.get(
    route('users.index'),
    { ...props.filters, status },
    {
      preserveState: true,  // Keep expandedRows, viewMode, selectedIds
      preserveScroll: true, // Keep scroll position too
    }
  )
}

// Search with state preservation
function handleSearch(search: string) {
  router.get(
    route('users.index'),
    { ...props.filters, search },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true, // Don't add to history for each keystroke
    }
  )
}

function toggleExpanded(id: number) {
  const idx = expandedRows.value.indexOf(id)
  if (idx === -1) expandedRows.value.push(id)
  else expandedRows.value.splice(idx, 1)
}

function toggleSelected(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx === -1) selectedIds.value.push(id)
  else selectedIds.value.splice(idx, 1)
}
</script>

<template>
  <div>
    <!-- Search input -->
    <input
      type="search"
      :value="props.filters.search"
      placeholder="Search users..."
      @input="(e) => handleSearch((e.target as HTMLInputElement).value)"
    />

    <!-- Status filter tabs -->
    <div class="flex gap-2 border-b">
      <button
        v-for="status in ['all', 'active', 'inactive', 'pending']"
        :key="status"
        :class="[
          'px-4 py-2',
          props.filters.status === status
            ? 'border-b-2 border-blue-500 text-blue-600'
            : 'text-gray-500',
        ]"
        @click="filterByStatus(status)"
      >
        {{ status.charAt(0).toUpperCase() + status.slice(1) }}
      </button>
    </div>

    <!-- View mode toggle - persists across filter changes -->
    <div class="flex gap-2">
      <button
        :class="viewMode === 'list' ? 'bg-blue-100' : ''"
        @click="viewMode = 'list'"
      >
        List View
      </button>
      <button
        :class="viewMode === 'grid' ? 'bg-blue-100' : ''"
        @click="viewMode = 'grid'"
      >
        Grid View
      </button>
    </div>

    <!-- Bulk actions using preserved selection -->
    <div v-if="selectedIds.length > 0" class="bg-blue-50 p-4">
      <span>{{ selectedIds.length }} users selected</span>
    </div>

    <!-- User list/grid -->
    <div :class="viewMode === 'grid' ? 'grid grid-cols-3 gap-4' : 'space-y-2'">
      <UserCard
        v-for="user in props.users.data"
        :key="user.id"
        :user="user"
        :view-mode="viewMode"
        :expanded="expandedRows.includes(user.id)"
        :selected="selectedIds.includes(user.id)"
        @toggle-expand="toggleExpanded(user.id)"
        @toggle-select="toggleSelected(user.id)"
      />
    </div>

    <!-- Pagination with state preservation -->
    <Pagination
      :links="props.users.links"
      :preserve-state="true"
      :preserve-scroll="true"
    />
  </div>
</template>
```

## Why

Preserving state during navigation provides a better user experience:

1. **UI Continuity**: Expanded rows, view modes, and selections persist
2. **Filter Experience**: Users can filter without losing their context
3. **Pagination**: Navigate pages without resetting UI preferences
4. **Reduced Friction**: No need to re-select items or re-expand details
5. **Natural Feel**: Behaves like a traditional SPA or desktop application
6. **Complementary**: Combine with preserveScroll for complete state retention
7. **Selective Use**: Only preserve state when it makes sense for the interaction
