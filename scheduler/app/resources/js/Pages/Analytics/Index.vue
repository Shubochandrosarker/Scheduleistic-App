<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    totals: Object,
});

// Metric keys the collector writes, in the order they matter to a report:
// how far it went, what it earned, what it converted. Anything not listed
// still renders — just with a humanized label and a neutral tone.
const KNOWN = {
    impressions: { label: 'Impressions', tone: 'accent' },
    reach: { label: 'Reach', tone: 'accent' },
    engagements: { label: 'Engagements', tone: 'ok' },
    likes: { label: 'Likes', tone: 'ok' },
    comments: { label: 'Comments', tone: 'alt' },
    shares: { label: 'Shares', tone: 'alt' },
    clicks: { label: 'Link clicks', tone: 'neutral' },
    video_views: { label: 'Video views', tone: 'neutral' },
};

const ORDER = Object.keys(KNOWN);

// Sorted into reporting order — reach, then engagement, then conversion — so the
// four stat cards are always the four that matter, whatever order the API used.
const entries = computed(() => Object.entries(props.totals ?? {})
    .map(([key, value]) => ({
        key,
        value: Number(value ?? 0),
        label: KNOWN[key]?.label ?? key.replace(/_/g, ' '),
        tone: KNOWN[key]?.tone ?? 'neutral',
    }))
    .sort((a, b) => {
        // Unrecognised metrics sort after every known one, then alphabetically.
        const ai = ORDER.indexOf(a.key);
        const bi = ORDER.indexOf(b.key);
        return (ai === -1 ? ORDER.length : ai) - (bi === -1 ? ORDER.length : bi)
            || a.key.localeCompare(b.key);
    }));

const hasData = computed(() => entries.value.length > 0);

// Headline metrics get stat cards; the rest go in a ranked bar list so the
// page stays readable no matter how many metrics a network reports.
const headline = computed(() => entries.value.slice(0, 4));
const rest = computed(() => entries.value.slice(4));

const max = computed(() => Math.max(1, ...rest.value.map((e) => e.value)));

const format = (n) => n.toLocaleString();
</script>

<template>
    <AppLayout title="Analytics">
        <PageHeader eyebrow="Performance">
            <template #title>Analytics</template>
            <template #subtitle>
                Per-post engagement, captured on a schedule by the metrics job — not scraped at
                page load, so these numbers are the same ones your reports use.
            </template>
            <template #actions>
                <SButton :href="route('posts.index')">Open calendar</SButton>
            </template>
        </PageHeader>

        <SCard v-if="!hasData" flush>
            <EmptyState
                icon="chart"
                title="No metrics yet"
                description="Once posts publish, engagement is captured hourly and totalled here."
            >
                <SButton variant="primary" :href="route('posts.create')">Compose a post →</SButton>
            </EmptyState>
        </SCard>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    v-for="e in headline"
                    :key="e.key"
                    :label="e.label"
                    :value="format(e.value)"
                    :tone="e.tone"
                    delta="All channels · lifetime"
                />
            </div>

            <SCard v-if="rest.length" class="mt-4" title="Every captured metric" subtitle="Totalled across all connected channels.">
                <ul class="space-y-4">
                    <li v-for="e in rest" :key="e.key">
                        <div class="mb-1.5 flex items-baseline justify-between gap-3">
                            <span class="text-[13px] font-semibold capitalize text-t2">{{ e.label }}</span>
                            <span class="text-[13px] font-extrabold text-t1">{{ format(e.value) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full" style="background: var(--line)">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: Math.max(2, (e.value / max) * 100) + '%',
                                    background: 'linear-gradient(90deg, var(--sc-accent-2), var(--sc-accent))',
                                }"
                            ></div>
                        </div>
                    </li>
                </ul>
            </SCard>
        </template>
    </AppLayout>
</template>
