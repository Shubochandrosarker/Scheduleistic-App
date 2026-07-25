<script setup>
defineProps({
    // Optional heading row rendered above the body, with a slot for actions.
    title: String,
    subtitle: String,
    // Drops the inner padding for cards that own their own layout (tables, grids).
    flush: Boolean,
});
</script>

<template>
    <section class="sc-card">
        <header
            v-if="title || $slots.header || $slots.actions"
            class="flex items-start justify-between gap-3 px-5 py-4"
            :class="{ 'border-b border-line': flush }"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h3 class="truncate text-[15px] font-bold text-t1">{{ title }}</h3>
                    <p v-if="subtitle" class="mt-0.5 text-[12.5px] text-t3">{{ subtitle }}</p>
                </slot>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div :class="flush ? '' : (title || $slots.header ? 'px-5 pb-5' : 'p-5')">
            <slot />
        </div>
    </section>
</template>
