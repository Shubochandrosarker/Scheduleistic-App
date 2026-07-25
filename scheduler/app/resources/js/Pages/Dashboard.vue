<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import MeterBar from '@/Components/UI/MeterBar.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    stats: Object,
    plan: String,
    usage: Object,
    upcoming: Array,
});

const page = usePage();
const branding = computed(() => page.props.branding ?? {});

// The four headline numbers, ordered the way an operator reads the day:
// what's queued, what shipped, who it's for, what it publishes through.
const kpis = computed(() => [
    {
        label: 'Scheduled posts',
        value: props.stats?.scheduled ?? 0,
        delta: 'Queued and waiting on the dispatcher',
        tone: 'accent',
        icon: '↑',
    },
    {
        label: 'Published this month',
        value: props.stats?.published_month ?? 0,
        delta: 'Delivered by the publishing engine',
        tone: 'ok',
        icon: '✓',
    },
    {
        label: 'Client workspaces',
        value: props.stats?.workspaces ?? 0,
        delta: 'Each one sealed off from the others',
        tone: 'alt',
        icon: '◆',
    },
    {
        label: 'Connected channels',
        value: props.stats?.channels ?? 0,
        delta: 'Across every workspace',
        tone: 'neutral',
        icon: '◌',
    },
]);

// A headline that reacts to the actual state of the account rather than
// greeting everyone identically.
const headline = computed(() => {
    const scheduled = props.stats?.scheduled ?? 0;
    const channels = props.stats?.channels ?? 0;

    if (channels === 0) return 'Connect a channel and the engine takes it from there.';
    if (scheduled === 0) return 'Nothing queued. The calendar is yours to fill.';

    return `${scheduled} post${scheduled === 1 ? '' : 's'} teed up and ready to ship.`;
});

const today = new Date().toLocaleDateString(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const meterTones = { monthly_posts: 'accent', workspaces: 'alt', channels: 'cyan', members: 'ok' };

const planLabel = computed(
    () => (props.plan ? props.plan.charAt(0).toUpperCase() + props.plan.slice(1) : 'Current'),
);

// "Sun 26 Jul" over "10:30" — two short lines beat one wrapping timestamp.
const slotDay = (iso) => (iso
    ? new Date(iso).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })
    : '—');

const slotTime = (iso) => (iso
    ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : '');
</script>

<template>
    <AppLayout title="Dashboard">
        <PageHeader :eyebrow="today">
            <template #title>{{ headline }}</template>
            <template #subtitle>
                Everything queued for {{ branding.name || 'Scheduleistic' }} — plus what needs a decision.
            </template>
            <template #actions>
                <SButton :href="route('posts.index')">Open calendar</SButton>
                <SButton variant="primary" :href="route('posts.create')">Compose a post →</SButton>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="k in kpis"
                :key="k.label"
                :label="k.label"
                :value="k.value"
                :delta="k.delta"
                :tone="k.tone"
                :icon="k.icon"
            />
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
            <!-- Going out next -->
            <SCard flush title="Going out next" subtitle="The next five posts the dispatcher will claim.">
                <template #actions>
                    <Link :href="route('posts.index')" class="text-[12.5px] font-bold" style="color: var(--sc-accent-text)">
                        Week view →
                    </Link>
                </template>

                <EmptyState
                    v-if="!upcoming || upcoming.length === 0"
                    icon="◷"
                    title="Nothing scheduled yet"
                    description="Compose a post and either pick an exact time or drop it in the queue."
                >
                    <SButton variant="primary" :href="route('posts.create')">Compose one →</SButton>
                </EmptyState>

                <ul v-else>
                    <!-- Narrow screens stack the timestamp above the copy; from `sm` up
                         it becomes the left gutter the design uses. -->
                    <li
                        v-for="p in upcoming"
                        :key="p.id"
                        class="sc-row flex flex-col gap-1.5 px-5 py-3.5 sm:flex-row sm:items-start sm:gap-4"
                    >
                        <div class="flex shrink-0 items-baseline gap-2 sm:w-24 sm:flex-col sm:gap-0">
                            <p class="text-[12.5px] font-extrabold leading-snug text-t1">{{ slotDay(p.scheduled_at) }}</p>
                            <p class="text-[11.5px] text-t3">{{ slotTime(p.scheduled_at) }}</p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13.5px] text-t2">{{ p.content || '(no content)' }}</p>
                            <p class="mt-0.5 text-[11.5px] text-t4">{{ p.workspace }}</p>
                        </div>

                        <SBadge class="self-start" tone="ok">Scheduled</SBadge>
                    </li>
                </ul>
            </SCard>

            <!-- Plan usage -->
            <SCard title="Plan usage" :subtitle="`${planLabel} plan, this cycle.`">
                <template #actions>
                    <Link :href="route('billing.index')" class="text-[12.5px] font-bold" style="color: var(--sc-accent-text)">
                        Manage →
                    </Link>
                </template>

                <div class="space-y-4">
                    <MeterBar
                        v-for="(meter, key) in usage"
                        :key="key"
                        :label="String(key).replace(/_/g, ' ')"
                        :usage="meter.usage"
                        :limit="meter.limit"
                        :tone="meterTones[key] || 'accent'"
                    />
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2.5">
                    <SButton size="sm" :href="route('workspaces.index')">Clients</SButton>
                    <SButton size="sm" :href="route('analytics.index')">Analytics</SButton>
                </div>
            </SCard>
        </div>
    </AppLayout>
</template>
