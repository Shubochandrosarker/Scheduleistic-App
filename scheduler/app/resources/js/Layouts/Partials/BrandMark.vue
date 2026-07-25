<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    size: { type: Number, default: 30 },
});

const page = usePage();
const branding = computed(() => page.props.branding ?? {});
</script>

<template>
    <!-- A tenant logo wins; otherwise the gradient glyph picks up their accent. -->
    <img
        v-if="branding.logo"
        :src="branding.logo"
        :alt="branding.name || 'Logo'"
        class="shrink-0 rounded-[9px] object-contain"
        :style="{ width: size + 'px', height: size + 'px' }"
    />
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-[9px]"
        :style="{
            width: size + 'px',
            height: size + 'px',
            background: 'linear-gradient(135deg, var(--sc-accent), var(--sc-accent-2))',
            boxShadow: '0 6px 18px var(--sc-mid)',
        }"
    >
        <svg
            :width="size * 0.6"
            :height="size * 0.6"
            viewBox="0 0 24 24"
            fill="none"
            stroke="var(--on-acc)"
            stroke-width="2.4"
            stroke-linecap="round"
        >
            <path d="M4 7h11M4 12h7M4 17h9M16 14l4 3-4 3" />
        </svg>
    </span>
</template>
