<script setup>
import { computed } from 'vue';

const props = defineProps({
    // Maps to a semantic token; `accent` follows the tenant's brand colour.
    tone: {
        type: String,
        default: 'neutral', // neutral | accent | ok | warn | bad | alt
    },
});

// Each tone is a tinted fill + a matching border, so badges stay legible on
// both the dark glass card and the light surface without a second variant.
const TONES = {
    neutral: { fg: 'var(--t3)', bg: 'var(--s3)', bd: 'var(--line2)' },
    accent: { fg: 'var(--sc-accent-text)', bg: 'var(--sc-soft)', bd: 'var(--sc-mid)' },
    ok: { fg: 'var(--ok)', bg: 'rgba(52,211,153,0.14)', bd: 'rgba(52,211,153,0.3)' },
    warn: { fg: 'var(--warn)', bg: 'rgba(245,158,11,0.14)', bd: 'rgba(245,158,11,0.3)' },
    bad: { fg: 'var(--bad)', bg: 'rgba(248,113,113,0.14)', bd: 'rgba(248,113,113,0.3)' },
    alt: { fg: 'var(--alt)', bg: 'rgba(167,139,250,0.14)', bd: 'rgba(167,139,250,0.3)' },
};

const style = computed(() => {
    const t = TONES[props.tone] ?? TONES.neutral;
    return { color: t.fg, background: t.bg, border: `1px solid ${t.bd}` };
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold"
        :style="style"
    >
        <slot />
    </span>
</template>
