<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Renders the plain-text description typed in the dashboard. Blank lines
 * split paragraphs; a run of lines starting with a bullet (•, ·, -, *)
 * becomes a feature grid instead of a column of loose dots. A short line
 * ending in a colon right before the list ("Features:") becomes its heading.
 */
const props = defineProps<{ text: string }>();

const { t } = useI18n();

type Block = { kind: 'paragraph'; text: string } | { kind: 'features'; title: string | null; items: string[] };

const BULLET = /^\s*[•·\-*▪●]\s+/;
const LABEL = /^(.{1,60})[:：]$/;

const blocks = computed<Block[]>(() => {
    const result: Block[] = [];

    for (const chunk of props.text.replace(/\r\n?/g, '\n').split(/\n\s*\n/)) {
        const lines = chunk
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);
        let prose: string[] = [];

        const flushProse = () => {
            if (prose.length > 0) {
                result.push({ kind: 'paragraph', text: prose.join(' ') });
                prose = [];
            }
        };

        for (const line of lines) {
            if (BULLET.test(line)) {
                flushProse();
                const item = line.replace(BULLET, '');
                const last = result[result.length - 1];

                if (last?.kind === 'features') {
                    last.items.push(item);
                } else {
                    const label = last?.kind === 'paragraph' ? last.text.match(LABEL) : null;

                    if (label) {
                        result.pop();
                    }

                    result.push({ kind: 'features', title: label ? label[1].trim() : null, items: [item] });
                }
            } else {
                prose.push(line);
            }
        }

        flushProse();
    }

    return result;
});
</script>

<template>
    <div class="product-description">
        <template v-for="(block, index) in blocks" :key="index">
            <p v-if="block.kind === 'paragraph'" class="product-description-text" :class="{ 'product-description-lead': index === 0 }">
                {{ block.text }}
            </p>

            <div v-else class="product-description-features">
                <h3 class="product-description-heading">{{ block.title ?? t('product.key_features') }}</h3>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="item in block.items" :key="item" class="product-description-feature">
                        <span class="product-description-check" aria-hidden="true"><Check class="h-3.5 w-3.5" stroke-width="3" /></span>
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>

<style>
.product-description {
    display: flex;
    max-width: 52rem;
    flex-direction: column;
    gap: 1.25rem;
}

.product-description-text {
    margin: 0;
    max-width: 68ch;
    color: var(--st-ink-soft);
    font-size: 1rem;
    line-height: 1.8;
}

.product-description-lead {
    color: var(--st-ink);
    font-size: 1.075rem;
}

.product-description-features {
    margin-top: 0.5rem;
}

.product-description-heading {
    margin: 0 0 0.875rem;
    color: var(--st-ink);
    font-size: 1rem;
    font-weight: 600;
}

.product-description-feature {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    border: 1px solid var(--st-line);
    border-radius: 0.75rem;
    background: var(--st-surface);
    padding: 0.875rem 1rem;
    color: var(--st-ink);
    font-size: 0.925rem;
    line-height: 1.5;
}

.product-description-check {
    display: flex;
    height: 1.375rem;
    width: 1.375rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgb(var(--st-accent-rgb) / 0.14);
    color: var(--st-accent-text);
}
</style>
