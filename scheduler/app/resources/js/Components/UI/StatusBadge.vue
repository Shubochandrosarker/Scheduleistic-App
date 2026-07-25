<script setup>
import { computed } from 'vue';
import SBadge from '@/Components/UI/SBadge.vue';

const props = defineProps({
    status: String,
});

// The post lifecycle, as stored on posts.status, mapped to a tone + a label
// that reads like English rather than a database enum.
const MAP = {
    draft: { tone: 'neutral', label: 'Draft' },
    pending_approval: { tone: 'warn', label: 'In review' },
    changes_requested: { tone: 'bad', label: 'Changes requested' },
    approved: { tone: 'accent', label: 'Approved' },
    rejected: { tone: 'bad', label: 'Rejected' },
    scheduled: { tone: 'ok', label: 'Scheduled' },
    publishing: { tone: 'warn', label: 'Publishing' },
    published: { tone: 'ok', label: 'Published' },
    partially_failed: { tone: 'warn', label: 'Partly failed' },
    failed: { tone: 'bad', label: 'Failed' },
};

const meta = computed(
    () => MAP[props.status] ?? { tone: 'neutral', label: String(props.status ?? '—').replace(/_/g, ' ') },
);
</script>

<template>
    <SBadge :tone="meta.tone">{{ meta.label }}</SBadge>
</template>
