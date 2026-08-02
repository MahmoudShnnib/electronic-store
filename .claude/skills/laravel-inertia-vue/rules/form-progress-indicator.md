---
section: forms
priority: high
description: Show upload progress and processing states for better user feedback in Vue 3
keywords: [progress, indicator, upload, processing, feedback, ux, vue3]
---

# Form Progress Indicator

Show upload progress for forms with file uploads and processing states for all form submissions.

## Bad Example

```vue
<!-- Anti-pattern: No feedback during submission -->
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({ document: null })
</script>

<template>
  <form @submit.prevent="form.post('/documents')">
    <input type="file" @change="(e) => form.document = e.target.files[0]" />
    <button type="submit">Upload</button>
    <!-- No progress indicator - user has no idea if upload is working -->
  </form>
</template>
```

## Good Example

```vue
<!-- resources/js/Components/ProgressBar.vue -->
<script setup lang="ts">
interface ProgressData {
  percentage: number
  loaded?: number
  total?: number
}

interface Props {
  progress: ProgressData | null
  showBytes?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showBytes: false,
})

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>

<template>
  <div v-if="props.progress" class="w-full">
    <div class="mb-1 flex justify-between text-sm text-gray-600">
      <span>Uploading...</span>
      <span>
        {{ props.progress.percentage }}%
        <span
          v-if="props.showBytes && props.progress.loaded && props.progress.total"
          class="ml-2 text-gray-400"
        >
          ({{ formatBytes(props.progress.loaded) }} / {{ formatBytes(props.progress.total) }})
        </span>
      </span>
    </div>
    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
      <div
        class="h-full rounded-full bg-blue-600 transition-all duration-300 ease-out"
        :style="{ width: `${props.progress.percentage}%` }"
      />
    </div>
  </div>
</template>
```

```vue
<!-- resources/js/Pages/Documents/Upload.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import ProgressBar from '@/Components/ProgressBar.vue'

type UploadState = 'idle' | 'uploading' | 'processing' | 'complete'

const uploadState = ref<UploadState>('idle')

const form = useForm({
  title: '',
  document: null as File | null,
})

function submit() {
  form.post(route('documents.store'), {
    onStart: () => (uploadState.value = 'uploading'),
    onProgress: (progress) => {
      if (progress.percentage === 100) {
        uploadState.value = 'processing'
      }
    },
    onSuccess: () => {
      uploadState.value = 'complete'
      setTimeout(() => {
        uploadState.value = 'idle'
        form.reset()
      }, 2000)
    },
    onError: () => (uploadState.value = 'idle'),
  })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6">
    <div>
      <label for="title" class="block text-sm font-medium">Document Title</label>
      <input
        id="title"
        v-model="form.title"
        type="text"
        :disabled="form.processing"
        class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100"
      />
    </div>

    <div>
      <label for="document" class="block text-sm font-medium">Document File</label>
      <input
        id="document"
        type="file"
        :disabled="form.processing"
        class="mt-1 block w-full disabled:opacity-50"
        @change="(e) => form.document = (e.target as HTMLInputElement).files?.[0] || null"
      />
      <p v-if="form.errors.document" class="mt-1 text-sm text-red-600">
        {{ form.errors.document }}
      </p>
    </div>

    <!-- Progress indicator -->
    <ProgressBar
      v-if="uploadState === 'uploading'"
      :progress="form.progress"
      :show-bytes="true"
    />

    <!-- Processing state (after upload, server is working) -->
    <div
      v-if="uploadState === 'processing'"
      class="flex items-center gap-2 text-sm text-gray-600"
    >
      <svg class="h-5 w-5 animate-spin text-blue-600" viewBox="0 0 24 24">
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
          fill="none"
        />
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
        />
      </svg>
      <span>Processing document...</span>
    </div>

    <!-- Success state -->
    <div
      v-if="uploadState === 'complete'"
      class="flex items-center gap-2 text-sm text-green-600"
    >
      <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
        <path
          fill-rule="evenodd"
          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
          clip-rule="evenodd"
        />
      </svg>
      <span>Upload complete!</span>
    </div>

    <button
      type="submit"
      :disabled="form.processing || !form.document"
      class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
    >
      <svg
        v-if="form.processing"
        class="h-4 w-4 animate-spin"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
          fill="none"
        />
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
        />
      </svg>
      <span>
        {{
          form.processing
            ? uploadState === 'processing'
              ? 'Processing...'
              : 'Uploading...'
            : 'Upload Document'
        }}
      </span>
    </button>
  </form>
</template>
```

## Why

Progress indicators are essential for good user experience:

1. **User Confidence**: Users know their action is being processed
2. **Large Files**: Essential for uploads that take more than a second
3. **Accurate Feedback**: Percentage shows actual progress, not just "loading"
4. **State Distinction**: Differentiate between uploading, server processing, and completion
5. **Prevent Duplicates**: Disabled buttons prevent accidental resubmission
6. **Accessibility**: Screen readers can announce progress updates
7. **Error Recovery**: Users understand when something goes wrong
