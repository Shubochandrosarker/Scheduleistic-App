<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'secondary', // primary | secondary | ghost | success | danger | warn
    },
    size: {
        type: String,
        default: 'md', // md | sm
    },
    // Render as an Inertia <Link> (href) or a plain <a> (external) instead of a button.
    href: String,
    external: Boolean,
    type: {
        type: String,
        default: 'button',
    },
    disabled: Boolean,
});

const classes = computed(() => [
    'sc-btn',
    `sc-btn-${props.variant}`,
    props.size === 'sm' ? 'sc-btn-sm' : '',
]);

const tag = computed(() => {
    if (!props.href) return 'button';
    return props.external ? 'a' : Link;
});
</script>

<template>
    <component
        :is="tag"
        :class="classes"
        :href="href"
        :type="href ? undefined : type"
        :disabled="href ? undefined : disabled"
        :aria-disabled="href && disabled ? 'true' : undefined"
    >
        <slot />
    </component>
</template>
