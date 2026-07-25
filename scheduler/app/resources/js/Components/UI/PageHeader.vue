<script setup>
defineProps({
    eyebrow: String,
    title: String,
    subtitle: String,
});
</script>

<template>
    <header class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-2 flex items-center gap-2 sc-eyebrow" style="color: var(--sc-accent-text)">
                <span class="size-1.5 rounded-full" style="background: var(--sc-accent)"></span>
                {{ eyebrow }}
            </p>
            <h1 class="sc-title">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-2 max-w-2xl sc-sub">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2.5">
            <slot name="actions" />
        </div>
    </header>
</template>
