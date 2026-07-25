<script setup>
import { useBrand } from '@/composables/useBrand';

// Kept for any Jetstream screen that still renders the centred-card variant of
// the auth layout; AuthLayout.vue is the split-screen one the product uses.
useBrand();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center px-5 pt-10 sm:justify-center sm:pt-0" style="background: var(--bg)">
        <div>
            <slot name="logo" />
        </div>

        <div class="sc-card mt-6 w-full overflow-hidden p-6 sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
