<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: String,
    value: [String, Number],
    // Small line under the value — a delta, a hint, a warning.
    delta: String,
    tone: {
        type: String,
        default: 'neutral', // neutral | accent | ok | warn | bad | alt
    },
    icon: String,
});

const TONES = {
    neutral: { fg: 'var(--t1)', soft: 'var(--s3)' },
    accent: { fg: 'var(--sc-accent-text)', soft: 'var(--sc-soft)' },
    ok: { fg: 'var(--ok)', soft: 'rgba(52,211,153,0.14)' },
    warn: { fg: 'var(--warn)', soft: 'rgba(245,158,11,0.14)' },
    bad: { fg: 'var(--bad)', soft: 'rgba(248,113,113,0.14)' },
    alt: { fg: 'var(--alt)', soft: 'rgba(167,139,250,0.14)' },
};

const tone = computed(() => TONES[props.tone] ?? TONES.neutral);
</script>

<template>
    <div class="sc-card p-5">
        <div class="flex items-start justify-between gap-3">
            <p class="sc-eyebrow">{{ label }}</p>
            <span
                v-if="icon"
                class="flex size-7 shrink-0 items-center justify-center rounded-lg text-[13px] font-extrabold"
                :style="{ color: tone.fg, background: tone.soft }"
            >{{ icon }}</span>
        </div>

        <p class="mt-3 text-[34px] font-extrabold leading-none tracking-[-0.03em] text-t1">
            {{ value }}
        </p>

        <p v-if="delta" class="mt-2.5 text-[12px] font-semibold" :style="{ color: tone.fg }">
            {{ delta }}
        </p>
    </div>
</template>
