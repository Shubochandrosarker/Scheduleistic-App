<script setup>
import { computed } from 'vue';
import { iconPaths } from '@/icons';

/**
 * The only way icons are rendered in Scheduleistic 2.0.
 *
 * `label` is what makes this accessible: pass one and the icon becomes an
 * `img` with a name; omit it and the icon is marked decorative so a screen
 * reader skips it. An icon-only *button* must always pass a label (or carry
 * its own aria-label) — SButton enforces that.
 */
const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 18 },
    strokeWidth: { type: [Number, String], default: 1.7 },
    /** Accessible name. Omit for decorative icons sitting next to visible text. */
    label: { type: String, default: null },
});

const paths = computed(() => iconPaths(props.name));
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="shrink-0"
        :role="label ? 'img' : 'presentation'"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : 'true'"
        focusable="false"
    >
        <title v-if="label">{{ label }}</title>
        <path v-for="(d, i) in paths" :key="i" :d="d" />
    </svg>
</template>
