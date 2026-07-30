<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

defineProps({
    size: { type: Number, default: 32 },
});

const page = usePage();
const { theme } = useTheme();

const branding = computed(() => page.props.branding ?? {});

// The platform accent. A tenant that has not set its own colour is not
// white-labelling, so it gets the real Scheduleistic mark; a tenant that has
// keeps the retinted glyph, because showing them our logo would defeat the
// feature they are paying for.
const PLATFORM_ACCENT = '#6366F1';

const usesPlatformBrand = computed(() => {
    const primary = String(branding.value.primary ?? PLATFORM_ACCENT).toUpperCase();

    return primary === PLATFORM_ACCENT;
});

// The mark ships in two cuts — one on the light chip, one on navy — so it sits
// correctly on either theme rather than being a light square on a dark rail.
const markSrc = computed(() => (theme.value === 'dark'
    ? '/brand/logo-mark-solid.svg'
    : '/brand/logo-mark.svg'));
</script>

<template>
    <!-- A tenant logo always wins. -->
    <img
        v-if="branding.logo"
        :src="branding.logo"
        :alt="branding.name || 'Logo'"
        class="shrink-0 rounded-[10px] object-contain"
        :style="{ width: size + 'px', height: size + 'px' }"
    />

    <!-- No tenant logo, platform branding: the Scheduleistic mark. -->
    <img
        v-else-if="usesPlatformBrand"
        :src="markSrc"
        alt=""
        aria-hidden="true"
        class="shrink-0"
        :style="{ width: size + 'px', height: size + 'px' }"
    />

    <!-- No tenant logo, but a custom accent: a neutral glyph in their colour. -->
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-[10px]"
        :style="{
            width: size + 'px',
            height: size + 'px',
            background: 'linear-gradient(135deg, var(--sc-accent-2), var(--sc-accent))',
            boxShadow: '0 6px 18px var(--sc-mid)',
        }"
    >
        <svg
            :width="size * 0.58"
            :height="size * 0.58"
            viewBox="0 0 24 24"
            fill="none"
            stroke="#fff"
            stroke-width="2.4"
            stroke-linecap="round"
            aria-hidden="true"
        >
            <path d="M4 7h11M4 12h7M4 17h9M16 14l4 3-4 3" />
        </svg>
    </span>
</template>
