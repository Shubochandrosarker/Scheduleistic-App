<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { network } from '@/networks';

defineProps({
    channel: Object,
    events: Object,
});

const SEVERITY_TONE = { info: 'neutral', warning: 'warn', critical: 'bad' };

const when = (value) => value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : null;
</script>

<template>
    <AppLayout :title="`${channel.name} — history`">
        <PageHeader eyebrow="Social profile health">
            <template #title>{{ channel.name }}</template>
            <template #subtitle>{{ network(channel.provider).label }} · {{ channel.workspace?.name }}</template>
            <template #actions>
                <Link :href="route('channels.health')" class="sc-btn sc-btn-sm sc-btn-secondary">Back to health</Link>
            </template>
        </PageHeader>

        <SCard class="mt-4" flush title="Full timeline">
            <template #actions>
                <span class="text-[11.5px] text-t4">Includes resolved events, not just open issues</span>
            </template>

            <EmptyState
                v-if="!events.data.length"
                icon="clock"
                title="No health events recorded"
                description="This profile has had no connection problems since it was connected."
            />

            <ul v-else class="divide-y" style="border-color: var(--line)">
                <li v-for="event in events.data" :key="event.id" class="flex items-start justify-between gap-3 px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-[13px] font-bold text-t1">
                            <span class="capitalize">{{ event.state.replace(/_/g, ' ') }}</span>
                            <SBadge :tone="event.resolved ? 'ok' : (SEVERITY_TONE[event.severity] ?? 'neutral')">
                                {{ event.resolved ? 'Resolved' : 'Open' }}
                            </SBadge>
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-t3">{{ event.message }}</p>
                    </div>
                    <div class="shrink-0 text-right text-[11.5px] text-t4">
                        <p>Detected {{ when(event.detected_at) }}</p>
                        <p v-if="event.resolved">Resolved {{ when(event.resolved_at) }}</p>
                    </div>
                </li>
            </ul>

            <nav v-if="events.links?.length > 3" class="flex flex-wrap justify-center gap-1 p-4" aria-label="Pagination">
                <template v-for="link in events.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll class="sc-pill" :class="link.active ? 'sc-pill-on' : ''" v-html="link.label" />
                    <span v-else class="sc-pill opacity-40" v-html="link.label" />
                </template>
            </nav>
        </SCard>
    </AppLayout>
</template>
