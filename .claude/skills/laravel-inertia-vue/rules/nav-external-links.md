---
section: navigation
priority: high
description: Use standard anchor tags for external links and file downloads in Vue 3
keywords: [external, links, download, anchor, security, noopener, vue3]
---

# External Links Handling

Use standard anchor tags for external links and file downloads, reserving Inertia's Link component for internal navigation only.

## Bad Example

```vue
<!-- Anti-pattern: Using Inertia Link for external URLs -->
<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <footer>
    <!-- These will cause Inertia to try to handle them as internal routes -->
    <Link href="https://twitter.com/mycompany">Twitter</Link>
    <Link href="https://github.com/mycompany">GitHub</Link>
    <Link href="/files/document.pdf">Download PDF</Link>
  </footer>
</template>
```

## Good Example

```vue
<!-- resources/js/Components/ExternalLink.vue -->
<script setup lang="ts">
interface Props {
  href: string
  showIcon?: boolean
  className?: string
}

const props = withDefaults(defineProps<Props>(), {
  showIcon: true,
  className: '',
})
</script>

<template>
  <a
    :href="props.href"
    target="_blank"
    rel="noopener noreferrer"
    :class="`inline-flex items-center gap-1 ${props.className}`"
  >
    <slot />
    <svg
      v-if="props.showIcon"
      class="h-4 w-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
      />
    </svg>
  </a>
</template>
```

```vue
<!-- resources/js/Components/DownloadLink.vue -->
<script setup lang="ts">
interface Props {
  href: string
  filename?: string
  className?: string
}

const props = withDefaults(defineProps<Props>(), {
  className: '',
})
</script>

<template>
  <a
    :href="props.href"
    :download="props.filename"
    :class="`inline-flex items-center gap-1 ${props.className}`"
  >
    <svg
      class="h-4 w-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
      />
    </svg>
    <slot />
  </a>
</template>
```

```vue
<!-- resources/js/Pages/Resources.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import ExternalLink from '@/Components/ExternalLink.vue'
import DownloadLink from '@/Components/DownloadLink.vue'

interface Document {
  id: number
  name: string
  url: string
  filename: string
  size: string
}

interface SocialLink {
  id: number
  platform: string
  url: string
}

interface Props {
  documents: Document[]
  socialLinks: SocialLink[]
}

const props = defineProps<Props>()

function isExternalUrl(url: string): boolean {
  try {
    const urlObj = new URL(url, window.location.origin)
    return urlObj.origin !== window.location.origin
  } catch {
    return false
  }
}
</script>

<template>
  <div class="space-y-8">
    <!-- Internal navigation uses Inertia Link -->
    <nav class="flex gap-4">
      <Link :href="route('resources.guides')">Guides</Link>
      <Link :href="route('resources.tutorials')">Tutorials</Link>
      <Link :href="route('resources.faq')">FAQ</Link>
    </nav>

    <!-- External links use regular anchors -->
    <section>
      <h2>Follow Us</h2>
      <div class="flex gap-4">
        <ExternalLink
          v-for="link in props.socialLinks"
          :key="link.id"
          :href="link.url"
          class-name="text-blue-600 hover:text-blue-800"
        >
          {{ link.platform }}
        </ExternalLink>
      </div>
    </section>

    <!-- File downloads use regular anchors with download attribute -->
    <section>
      <h2>Documents</h2>
      <ul class="space-y-2">
        <li v-for="doc in props.documents" :key="doc.id">
          <DownloadLink
            :href="doc.url"
            :filename="doc.filename"
            class-name="text-blue-600 hover:text-blue-800"
          >
            {{ doc.name }} ({{ doc.size }})
          </DownloadLink>
        </li>
      </ul>
    </section>
  </div>
</template>
```

## Why

Proper handling of external links and downloads is important because:

1. **Correct Behavior**: Inertia's Link is designed for internal SPA navigation only
2. **Security**: External links should use `rel="noopener noreferrer"` to prevent tabnabbing
3. **User Expectations**: External links should open in new tabs with visual indicators
4. **Downloads**: File downloads need regular anchors with download attribute
5. **SEO**: Search engines correctly follow standard anchor tags
6. **Accessibility**: Screen readers announce external links appropriately
7. **Error Prevention**: Using Inertia Link for external URLs causes errors or unexpected behavior
