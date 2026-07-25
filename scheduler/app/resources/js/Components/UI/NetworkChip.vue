<script setup>
import { computed } from 'vue';
import { network, networkSoft } from '@/networks';

const props = defineProps({
    provider: String,
    size: { type: String, default: 'md' }, // sm | md | lg
    // Show the network's name next to the glyph.
    withLabel: Boolean,
});

const meta = computed(() => network(props.provider));

const SIZES = {
    sm: 'size-6 text-[9px] rounded-md',
    md: 'size-8 text-[10px] rounded-lg',
    lg: 'size-10 text-[12px] rounded-xl',
};

const box = computed(() => SIZES[props.size] ?? SIZES.md);

const style = computed(() => ({
    color: meta.value.color,
    background: networkSoft(props.provider, 0.16),
    border: `1px solid ${networkSoft(props.provider, 0.28)}`,
}));
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <span
            class="inline-flex shrink-0 items-center justify-center font-extrabold leading-none"
            :class="box"
            :style="style"
            :title="meta.label"
        >{{ meta.short }}</span>
        <span v-if="withLabel" class="truncate text-[13px] font-semibold text-t2">{{ meta.label }}</span>
    </span>
</template>
