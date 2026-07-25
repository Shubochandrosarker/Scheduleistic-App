<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/Layouts/Partials/BrandMark.vue';

const page = usePage();
const branding = computed(() => page.props.branding ?? {});
</script>

<template>
    <!-- Wordmark: the brand glyph plus whatever the tenant named the product. -->
    <span class="inline-flex items-center gap-2.5">
        <BrandMark :size="34" />
        <span class="text-[17px] font-extrabold tracking-[-0.02em] text-t1">
            {{ branding.name || 'Scheduleistic' }}
        </span>
    </span>
</template>
