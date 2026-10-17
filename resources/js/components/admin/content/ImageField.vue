<script setup lang="ts">
/**
 * Artwork for a hero slide, promo tile or banner. The file is uploaded as
 * soon as it is picked (admin.pages.images.store) and only its path is kept,
 * so the section itself saves as plain JSON.
 */
import { Button, Upload, message } from 'ant-design-vue';
import { ImageOff, ImagePlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { readXsrfToken } from './types';

const props = defineProps<{
    modelValue: string | null | undefined;
    storageUrl: string;
    /** Shown when no artwork is set, e.g. the product's own photo. */
    fallback?: string | null;
}>();

const emit = defineEmits<{ (e: 'update:modelValue', value: string | null): void }>();

const { t } = useI18n();
const uploading = ref(false);

const src = computed(() => (props.modelValue ? `${props.storageUrl}/${props.modelValue}` : (props.fallback ?? null)));

async function upload(file: File): Promise<void> {
    uploading.value = true;

    const body = new FormData();
    body.append('image', file);

    try {
        const response = await fetch(route('admin.pages.images.store'), {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': readXsrfToken() },
            credentials: 'same-origin',
        });
        const json = await response.json();

        if (!response.ok) {
            message.error(json.errors?.image?.[0] ?? json.message ?? t('admin.pages.upload_failed'));
            return;
        }

        emit('update:modelValue', json.path as string);
    } catch {
        message.error(t('admin.pages.upload_failed'));
    } finally {
        uploading.value = false;
    }
}

/** Returning false keeps AntD from uploading on its own. */
function choose(file: File): boolean {
    void upload(file);

    return false;
}
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="builder-thumb">
            <img v-if="src" :src="src" alt="" width="72" height="72" />
            <ImageOff v-else class="h-5 w-5 opacity-40" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <Upload :show-upload-list="false" accept="image/jpeg,image/png,image/webp" :before-upload="choose">
                <Button size="small" :loading="uploading">
                    <span class="flex items-center gap-1.5">
                        <ImagePlus v-if="!uploading" class="h-4 w-4" />
                        {{ modelValue ? t('admin.homepage.replace') : t('admin.homepage.upload') }}
                    </span>
                </Button>
            </Upload>
            <Button v-if="modelValue" size="small" type="text" danger @click="emit('update:modelValue', null)">
                {{ t('admin.homepage.remove_image') }}
            </Button>
        </div>
    </div>
</template>

<style scoped>
.builder-thumb {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid var(--ad-line);
    background: var(--ad-bg);
}

.builder-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
