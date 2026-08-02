---
section: forms
priority: high
description: Handle file uploads with progress tracking and preview functionality in Vue 3
keywords: [file, upload, formdata, progress, preview, multipart, vue3]
---

# Form File Uploads

Handle file uploads with Inertia using the useForm composable with proper progress tracking and preview functionality.

## Bad Example

```vue
<!-- Anti-pattern: Manual FormData handling -->
<script setup>
import { ref } from 'vue'

const file = ref(null)

async function handleSubmit() {
  const formData = new FormData()
  formData.append('avatar', file.value)

  await fetch('/upload', {
    method: 'POST',
    body: formData,
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
  })
}
</script>
```

## Good Example

```vue
<!-- resources/js/Pages/Profile/Edit.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

interface User {
  name: string
  email: string
  avatar_url: string | null
}

interface Props {
  user: User
}

const props = defineProps<Props>()

const preview = ref<string | null>(props.user.avatar_url)
const fileInputRef = ref<HTMLInputElement | null>(null)

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  avatar: null as File | null,
})

function handleFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  // Validate file on client side
  if (file.size > 5 * 1024 * 1024) {
    alert('File must be less than 5MB')
    return
  }

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    alert('File must be an image (JPEG, PNG, or WebP)')
    return
  }

  form.avatar = file

  // Create preview
  const reader = new FileReader()
  reader.onloadend = () => (preview.value = reader.result as string)
  reader.readAsDataURL(file)
}

function removeFile() {
  form.avatar = null
  preview.value = props.user.avatar_url
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

function submit() {
  form.post(route('profile.update'), {
    forceFormData: true,
    onSuccess: () => {
      if (fileInputRef.value) {
        fileInputRef.value.value = ''
      }
    },
  })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6">
    <div>
      <label class="block text-sm font-medium text-gray-700">Profile Photo</label>

      <div class="mt-2 flex items-center gap-4">
        <!-- Preview -->
        <div class="h-20 w-20 overflow-hidden rounded-full bg-gray-100">
          <img
            v-if="preview"
            :src="preview"
            alt="Avatar preview"
            class="h-full w-full object-cover"
          />
          <div
            v-else
            class="flex h-full w-full items-center justify-center text-gray-400"
          >
            No image
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <input
            ref="fileInputRef"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="text-sm text-gray-500"
            @change="handleFileChange"
          />
          <button
            v-if="form.avatar"
            type="button"
            class="text-sm text-red-600 hover:text-red-800"
            @click="removeFile"
          >
            Remove new photo
          </button>
        </div>
      </div>

      <p v-if="form.errors.avatar" class="mt-2 text-sm text-red-600">
        {{ form.errors.avatar }}
      </p>
    </div>

    <!-- Progress bar for file upload -->
    <div v-if="form.progress" class="w-full">
      <div class="mb-1 flex justify-between text-sm">
        <span>Uploading...</span>
        <span>{{ form.progress.percentage }}%</span>
      </div>
      <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
        <div
          class="h-full bg-indigo-600 transition-all duration-300"
          :style="{ width: `${form.progress.percentage}%` }"
        />
      </div>
    </div>

    <!-- Other form fields -->
    <div>
      <label for="name">Name</label>
      <input
        id="name"
        v-model="form.name"
        class="mt-1 block w-full rounded-md border-gray-300"
      />
      <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">
        {{ form.errors.name }}
      </p>
    </div>

    <button
      type="submit"
      :disabled="form.processing"
      class="rounded-md bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
    >
      {{ form.processing ? 'Saving...' : 'Save Changes' }}
    </button>
  </form>
</template>
```

```vue
<!-- Multiple file uploads -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  title: '',
  images: [] as File[],
})

function handleMultipleFiles(e: Event) {
  const files = Array.from((e.target as HTMLInputElement).files || [])
  form.images = [...form.images, ...files]
}

function removeImage(index: number) {
  form.images = form.images.filter((_, i) => i !== index)
}
</script>

<template>
  <form @submit.prevent="form.post('/gallery')">
    <input type="file" multiple accept="image/*" @change="handleMultipleFiles" />
    <div class="grid grid-cols-4 gap-2">
      <div v-for="(file, index) in form.images" :key="index" class="relative">
        <img
          :src="URL.createObjectURL(file)"
          :alt="`Preview ${index}`"
          class="h-24 w-24 object-cover"
        />
        <button
          type="button"
          class="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white"
          @click="removeImage(index)"
        >
          X
        </button>
      </div>
    </div>
    <p v-if="form.errors.images" class="text-sm text-red-600">
      {{ form.errors.images }}
    </p>
  </form>
</template>
```

## Why

Using Inertia's built-in file upload handling provides:

1. **Automatic FormData**: Inertia converts data to FormData when files are present
2. **Progress Tracking**: Real-time upload progress for better UX
3. **CSRF Handling**: Tokens are automatically included
4. **Validation Integration**: Server-side errors display like regular field errors
5. **Simplified API**: No manual FormData construction or fetch calls
6. **Preview Support**: Client-side previews improve user experience
7. **Chunked Uploads**: For very large files, consider pairing with libraries like Filepond
