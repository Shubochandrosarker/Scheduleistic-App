<script setup>
defineProps({
    title: String,
    description: String,
    icon: { type: String, default: '◌' },
});
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <span
            class="mb-4 flex size-12 items-center justify-center rounded-2xl text-[20px]"
            style="background: var(--s3); color: var(--t4); border: 1px solid var(--line2)"
        >{{ icon }}</span>

        <p class="text-[15px] font-bold text-t1">{{ title }}</p>
        <p v-if="description" class="mt-1.5 max-w-sm text-[13px] leading-relaxed text-t3">{{ description }}</p>

        <div v-if="$slots.default" class="mt-5 flex flex-wrap items-center justify-center gap-2.5">
            <slot />
        </div>
    </div>
</template>
