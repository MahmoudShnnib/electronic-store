---
section: shared-data
priority: medium
description: Share app config and feature flags through Inertia middleware in Vue 3
keywords: [config, settings, feature-flags, environment, configuration, vue3]
---

# Shared App Configuration

Share application configuration through Inertia's middleware to provide consistent access to app settings, feature flags, and environment-specific values.

## Bad Example

```vue
<!-- Anti-pattern: Hardcoding configuration values -->
<template>
  <footer>
    <p>Contact: support@example.com</p>
    <p>Version: 1.0.0</p>
  </footer>
</template>

<!-- Anti-pattern: Fetching config separately -->
<script setup>
import { ref, onMounted } from 'vue'

const config = ref(null)
onMounted(async () => {
  const res = await fetch('/api/config')
  config.value = await res.json()
})
</script>
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
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'locale' => app()->getLocale(),
                'timezone' => config('app.timezone'),
                'version' => config('app.version', '1.0.0'),
            ],
            'config' => [
                'contact_email' => config('site.contact_email'),
                'support_phone' => config('site.support_phone'),
                'social' => [
                    'twitter' => config('site.social.twitter'),
                    'facebook' => config('site.social.facebook'),
                    'linkedin' => config('site.social.linkedin'),
                ],
            ],
            'features' => [
                'dark_mode' => config('features.dark_mode', false),
                'notifications' => config('features.notifications', true),
                'two_factor' => config('features.two_factor', false),
                'api_access' => config('features.api_access', false),
            ],
            'settings' => fn () => $request->user()?->settings ?? [],
        ]);
    }
}
```

```ts
// resources/js/types/index.d.ts
export interface AppConfig {
  name: string
  url: string
  locale: string
  timezone: string
  version: string
}

export interface SiteConfig {
  contact_email: string
  support_phone: string
  social: {
    twitter?: string
    facebook?: string
    linkedin?: string
  }
}

export interface Features {
  dark_mode: boolean
  notifications: boolean
  two_factor: boolean
  api_access: boolean
}

export interface PageProps {
  auth: { user: User | null }
  flash: FlashMessages
  app: AppConfig
  config: SiteConfig
  features: Features
  settings: Record<string, unknown>
}
```

```ts
// resources/js/composables/useConfig.ts
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { PageProps, Features } from '@/types'

export function useApp() {
  const page = usePage<PageProps>()
  return computed(() => page.props.app)
}

export function useConfig() {
  const page = usePage<PageProps>()
  return computed(() => page.props.config)
}

export function useFeatures() {
  const page = usePage<PageProps>()
  const features = computed(() => page.props.features)

  return {
    features,
    isEnabled: (feature: keyof Features) => features.value[feature] === true,
  }
}

export function useSettings() {
  const page = usePage<PageProps>()
  return computed(() => page.props.settings)
}
```

```vue
<!-- resources/js/Components/FeatureFlag.vue -->
<script setup lang="ts">
import { useFeatures } from '@/composables/useConfig'
import type { Features } from '@/types'
import { computed } from 'vue'

interface Props {
  feature: keyof Features
}

const props = defineProps<Props>()
const { isEnabled } = useFeatures()

const authorized = computed(() => isEnabled(props.feature))
</script>

<template>
  <slot v-if="authorized" />
  <slot v-else name="fallback" />
</template>
```

```vue
<!-- resources/js/Components/Footer.vue -->
<script setup lang="ts">
import { useApp, useConfig } from '@/composables/useConfig'
import ExternalLink from '@/Components/ExternalLink.vue'

const app = useApp()
const config = useConfig()
</script>

<template>
  <footer class="bg-gray-800 py-8 text-gray-300">
    <div class="mx-auto max-w-7xl px-4">
      <div class="grid grid-cols-3 gap-8">
        <!-- App info -->
        <div>
          <h3 class="text-lg font-semibold text-white">{{ app.name }}</h3>
          <p class="mt-2 text-sm">Version {{ app.version }}</p>
        </div>

        <!-- Contact -->
        <div>
          <h3 class="text-lg font-semibold text-white">Contact</h3>
          <p class="mt-2">
            <a :href="`mailto:${config.contact_email}`" class="hover:text-white">
              {{ config.contact_email }}
            </a>
          </p>
        </div>

        <!-- Social -->
        <div>
          <h3 class="text-lg font-semibold text-white">Follow Us</h3>
          <div class="mt-2 flex gap-4">
            <ExternalLink v-if="config.social.twitter" :href="config.social.twitter">
              Twitter
            </ExternalLink>
            <ExternalLink v-if="config.social.facebook" :href="config.social.facebook">
              Facebook
            </ExternalLink>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>
```

```vue
<!-- resources/js/Pages/Settings/Index.vue -->
<script setup lang="ts">
import FeatureFlag from '@/Components/FeatureFlag.vue'
import { useFeatures } from '@/composables/useConfig'

const { features } = useFeatures()
</script>

<template>
  <div class="space-y-6">
    <h1>Settings</h1>

    <FeatureFlag feature="dark_mode">
      <section>
        <h2>Appearance</h2>
        <DarkModeToggle />
      </section>
    </FeatureFlag>

    <FeatureFlag feature="two_factor">
      <section>
        <h2>Two-Factor Authentication</h2>
        <TwoFactorSetup />
      </section>
    </FeatureFlag>

    <section v-if="features.api_access">
      <h2>API Access</h2>
      <ApiTokenManager />
    </section>
  </div>
</template>
```

## Why

Sharing app configuration provides:

1. **Centralized Config**: All configuration flows from Laravel to Vue
2. **Type Safety**: TypeScript interfaces ensure config structure
3. **Feature Flags**: Easy toggle of features without code changes
4. **Environment Handling**: Correct values for dev/staging/production
5. **No Hardcoding**: Configuration values are maintainable in one place
6. **Lazy Loading**: Use closures for user-specific settings
7. **SSR Compatible**: Works correctly with server-side rendering
