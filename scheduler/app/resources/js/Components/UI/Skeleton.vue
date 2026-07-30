<script setup>
/**
 * Loading placeholder. `aria-hidden` because a screen reader should hear the
 * live region announcing "loading", not a description of grey rectangles.
 */
defineProps({
    lines: { type: Number, default: 3 },
    height: { type: String, default: '14px' },
});
</script>

<template>
    <div class="flex flex-col gap-2" aria-hidden="true">
        <div
            v-for="line in lines"
            :key="line"
            class="sc-skeleton"
            :style="{ height, width: line === lines ? '60%' : '100%' }"
        ></div>
    </div>
</template>
