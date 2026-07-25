<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: String,
    usage: { type: Number, default: 0 },
    // -1 means "unlimited" everywhere in the app's plan config.
    limit: { type: Number, default: 0 },
    tone: { type: String, default: 'accent' }, // accent | ok | warn | bad | alt | cyan
});

const TONES = {
    accent: 'var(--sc-accent)',
    ok: 'var(--ok)',
    warn: 'var(--warn)',
    bad: 'var(--bad)',
    alt: 'var(--alt)',
    cyan: 'var(--cy-text)',
};

const unlimited = computed(() => props.limit === -1);

const pct = computed(() => {
    // Unlimited still draws a sliver so the row doesn't look broken/empty.
    if (unlimited.value) return 8;
    return Math.min(100, Math.round((props.usage / Math.max(props.limit, 1)) * 100));
});

// A meter that is nearly spent should read as a warning regardless of the tone
// the page picked — running out of quota is the thing worth noticing.
const color = computed(() => {
    if (!unlimited.value && pct.value >= 90) return 'var(--bad)';
    if (!unlimited.value && pct.value >= 75) return 'var(--warn)';
    return TONES[props.tone] ?? TONES.accent;
});
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-3">
            <span class="text-[12.5px] font-semibold capitalize text-t2">{{ label }}</span>
            <span class="text-[12.5px] font-semibold text-t3">
                {{ usage }} <span class="text-t4">/ {{ unlimited ? '∞' : limit }}</span>
            </span>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full" style="background: var(--line)">
            <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: pct + '%', background: color }"
            ></div>
        </div>
    </div>
</template>
