<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import FilterBar from '@/Components/UI/FilterBar.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import { network } from '@/networks';
import { useTerms } from '@/composables/useCapabilities';

/**
 * The permanent post/schedule history: every past publish attempt across
 * every channel, server-paginated since this is expected to grow unboundedly
 * — unlike the planner, which is windowed to what's on screen.
 */
const props = defineProps({
    targets: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
});

const { many } = useTerms();

const filterState = ref({ ...props.filters });
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

// Re-sync local state after any navigation the FilterBar itself didn't
// drive (sorting a column, changing the date range) so a later facet toggle
// sends the filters the page is actually showing, not what was on screen at
// mount time.
watch(() => props.filters, (next) => {
    filterState.value = { ...next };
    from.value = next.from ?? '';
    to.value = next.to ?? '';
});

const facets = computed(() => [
    {
        key: 'workspace_ids',
        label: many('workspace'),
        options: (props.options.workspaces ?? []).map((w) => ({ value: w.id, label: w.name, color: w.color })),
    },
    {
        key: 'providers',
        label: many('provider'),
        options: (props.options.providers ?? []).map((p) => ({ value: p, label: network(p).label, color: network(p).color })),
    },
    {
        key: 'statuses',
        label: 'Status',
        options: (props.options.statuses ?? []).map((s) => ({ value: s, label: s.replace(/_/g, ' ') })),
    },
    {
        key: 'campaign_ids',
        label: 'Campaigns',
        options: (props.options.campaigns ?? []).map((c) => ({ value: c.id, label: c.name, color: c.color })),
    },
]);

const go = (params) => router.get(route('history.index'), { ...props.filters, ...params }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

const applyDates = () => go({ from: from.value || undefined, to: to.value || undefined });

const sortBy = (column) => go({
    sort: column,
    direction: props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc',
});

const exportHref = computed(() => route('history.export', props.filters));

const STATUS_TONE = { published: 'ok', failed: 'bad', publishing: 'warn', pending: 'neutral' };

const when = (value) => value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : '—';
</script>

<template>
    <AppLayout title="Post history">
        <PageHeader eyebrow="Every past publish attempt">
            <template #title>History</template>
            <template #subtitle>
                What was scheduled, what published, and what failed — across every {{ many('channel').toLowerCase() }}.
            </template>
            <template #actions>
                <a :href="exportHref" class="sc-btn sc-btn-sm sc-btn-secondary">
                    <SIcon name="download" :size="14" /> Export CSV
                </a>
            </template>
        </PageHeader>

        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div class="sc-card p-4">
                <p class="text-[12px] font-bold text-t4">Published</p>
                <p class="text-[22px] font-extrabold" style="color: var(--ok)">{{ summary.by_status?.published ?? 0 }}</p>
            </div>
            <div class="sc-card p-4">
                <p class="text-[12px] font-bold text-t4">Failed</p>
                <p class="text-[22px] font-extrabold" style="color: var(--bad)">{{ summary.by_status?.failed ?? 0 }}</p>
            </div>
            <div class="sc-card p-4">
                <p class="text-[12px] font-bold text-t4">Publishing</p>
                <p class="text-[22px] font-extrabold" style="color: var(--warn)">{{ summary.by_status?.publishing ?? 0 }}</p>
            </div>
            <div class="sc-card p-4">
                <p class="text-[12px] font-bold text-t4">Pending</p>
                <p class="text-[22px] font-extrabold text-t1">{{ summary.by_status?.pending ?? 0 }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-start gap-3">
            <FilterBar
                v-model="filterState"
                :facets="facets"
                route-name="history.index"
                :preserve="{ from: filters.from, to: filters.to, sort: filters.sort, direction: filters.direction }"
                class="min-w-0 flex-1"
            />
            <div class="flex items-center gap-2">
                <label class="flex flex-col text-[11px] font-bold text-t4">
                    From
                    <input v-model="from" type="date" class="sc-input mt-1 !py-2 !text-[12.5px]" aria-label="From date" @change="applyDates" />
                </label>
                <label class="flex flex-col text-[11px] font-bold text-t4">
                    To
                    <input v-model="to" type="date" class="sc-input mt-1 !py-2 !text-[12.5px]" aria-label="To date" @change="applyDates" />
                </label>
            </div>
        </div>

        <div v-if="summary.by_campaign?.length || summary.by_tag?.length" class="mt-4 grid gap-3 sm:grid-cols-2">
            <div v-if="summary.by_campaign?.length" class="sc-card p-4">
                <p class="mb-2 text-[11.5px] font-bold uppercase tracking-wide text-t4">Top campaigns in this view</p>
                <ul class="flex flex-col gap-1.5">
                    <li v-for="row in summary.by_campaign" :key="row.id" class="flex items-center justify-between gap-2 text-[13px]">
                        <span class="flex min-w-0 items-center gap-1.5 truncate text-t2">
                            <span class="size-2 shrink-0 rounded-full" :style="{ background: row.color || 'var(--t4)' }"></span>
                            <span class="truncate">{{ row.name }}</span>
                        </span>
                        <span class="shrink-0 font-bold text-t1">{{ row.total }}</span>
                    </li>
                </ul>
            </div>
            <div v-if="summary.by_tag?.length" class="sc-card p-4">
                <p class="mb-2 text-[11.5px] font-bold uppercase tracking-wide text-t4">Top tags in this view</p>
                <ul class="flex flex-col gap-1.5">
                    <li v-for="row in summary.by_tag" :key="row.id" class="flex items-center justify-between gap-2 text-[13px]">
                        <span class="flex min-w-0 items-center gap-1.5 truncate text-t2">
                            <span class="size-2 shrink-0 rounded-full" :style="{ background: row.color || 'var(--t4)' }"></span>
                            <span class="truncate">{{ row.name }}</span>
                        </span>
                        <span class="shrink-0 font-bold text-t1">{{ row.total }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <SCard class="mt-4" flush>
            <EmptyState
                v-if="!targets.data.length"
                icon="clock"
                title="No publish attempts yet"
                description="Once scheduled posts go out, every outcome — success or failure — will show up here."
            />

            <div v-else class="sc-scroll overflow-x-auto">
                <table class="w-full min-w-[860px] border-collapse text-left">
                    <thead>
                        <tr class="border-b" style="border-color: var(--line)">
                            <th class="px-5 py-3 sc-eyebrow font-bold">Post</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Channel</th>
                            <th class="px-5 py-3">
                                <button type="button" class="sc-eyebrow font-bold" @click="sortBy('scheduled_at')">Scheduled</button>
                            </th>
                            <th class="px-5 py-3">
                                <button type="button" class="sc-eyebrow font-bold" @click="sortBy('published_at')">Published</button>
                            </th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Status</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in targets.data" :key="row.id" class="sc-row">
                            <td class="px-5 py-3.5">
                                <p class="max-w-[260px] truncate text-[13px] font-bold text-t1">{{ row.post?.title || row.post?.excerpt || 'Untitled' }}</p>
                                <p class="text-[11.5px] text-t4">
                                    {{ row.post?.workspace?.name }}<span v-if="row.post?.campaign"> · {{ row.post.campaign.name }}</span>
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="flex items-center gap-1.5 text-[13px] text-t2">
                                    <span
                                        class="flex size-5 shrink-0 items-center justify-center rounded text-[9px] font-extrabold"
                                        :style="{ background: network(row.channel?.provider).color, color: '#fff' }"
                                    >{{ network(row.channel?.provider).short }}</span>
                                    {{ row.channel?.name }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-[12.5px] text-t3">{{ when(row.post?.scheduled_at) }}</td>
                            <td class="px-5 py-3.5 text-[12.5px] text-t3">{{ when(row.published_at) }}</td>
                            <td class="px-5 py-3.5">
                                <SBadge :tone="STATUS_TONE[row.status] ?? 'neutral'">{{ row.status }}</SBadge>
                                <p v-if="row.attempts > 1" class="mt-1 text-[11px] text-t4">{{ row.attempts }} attempts</p>
                            </td>
                            <td class="max-w-[260px] px-5 py-3.5 text-[12px] text-t3">
                                <span v-if="row.error" class="text-[color:var(--bad)]" :title="row.error">{{ row.error }}</span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="targets.links?.length > 3" class="flex flex-wrap justify-center gap-1 p-4" aria-label="Pagination">
                <template v-for="link in targets.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-state
                        preserve-scroll
                        class="sc-pill"
                        :class="link.active ? 'sc-pill-on' : ''"
                        :aria-current="link.active ? 'page' : undefined"
                        v-html="link.label"
                    />
                    <span v-else class="sc-pill opacity-40" v-html="link.label" />
                </template>
            </nav>
        </SCard>
    </AppLayout>
</template>
