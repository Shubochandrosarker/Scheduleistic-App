<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import FilterBar from '@/Components/UI/FilterBar.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import PostCard from '@/Pages/Planner/Partials/PostCard.vue';
import PostDrawer from '@/Pages/Planner/Partials/PostDrawer.vue';
import { useTerms } from '@/composables/useCapabilities';
import { network } from '@/networks';

/**
 * The content planner.
 *
 * Every view renders the same `posts` payload — the server decides *which*
 * posts by deriving a date window from the view, so switching from month to
 * week is one request that returns proportionally less data rather than a
 * client-side filter over everything.
 */
const props = defineProps({
    posts: { type: Array, default: () => [] },
    view: { type: String, default: 'month' },
    window: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
});

const { many, one } = useTerms();

const VIEWS = [
    { key: 'month', label: 'Month', icon: 'calendar' },
    { key: 'week', label: 'Week', icon: 'calendar' },
    { key: 'agenda', label: 'Agenda', icon: 'clock' },
    { key: 'list', label: 'List', icon: 'menu' },
    { key: 'grid', label: 'Grid preview', icon: 'image' },
];

const filterState = ref({ ...props.filters });
const openPostId = ref(null);
const selected = ref([]);
const dragging = ref(null);
const dropTarget = ref(null);

const anchor = computed(() => new Date(props.window.from ?? Date.now()));

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
    {
        key: 'pillar_ids',
        label: 'Pillars',
        options: (props.options.pillars ?? []).map((p) => ({ value: p.id, label: p.name, color: p.color })),
    },
    {
        key: 'tag_ids',
        label: 'Tags',
        options: (props.options.tags ?? []).map((t) => ({ value: t.id, label: t.name, color: t.color })),
    },
    {
        key: 'assignee_ids',
        label: 'Assignee',
        options: (props.options.assignees ?? []).map((a) => ({ value: a.id, label: a.name })),
    },
]);

// --- Calendar geometry -------------------------------------------------

const toKey = (date) => {
    const d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** Posts bucketed by local calendar day. Unscheduled posts get their own tray. */
const byDay = computed(() => {
    const buckets = {};

    for (const post of props.posts) {
        if (!post.scheduled_at) continue;
        const key = toKey(post.scheduled_at);
        (buckets[key] ??= []).push(post);
    }

    return buckets;
});

const unscheduled = computed(() => props.posts.filter((post) => !post.scheduled_at));

/** Six weeks of cells covering the month, Monday-first. */
const monthCells = computed(() => {
    const start = new Date(props.window.from ?? Date.now());
    const cells = [];

    for (let i = 0; i < 42; i++) {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        cells.push(date);

        if (i >= 34 && date > new Date(props.window.to)) break;
    }

    return cells;
});

const weekCells = computed(() => {
    const start = new Date(props.window.from ?? Date.now());

    return Array.from({ length: 7 }, (_, i) => {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        return date;
    });
});

/** Agenda: only days that actually have posts, in order. */
const agendaDays = computed(() => Object.keys(byDay.value)
    .sort()
    .map((key) => ({ key, date: new Date(`${key}T00:00:00`), posts: byDay.value[key] })));

const isToday = (date) => toKey(date) === toKey(new Date());
const inWindowMonth = (date) => date.getMonth() === anchor.value.getMonth();

const dayLabel = (date) => date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' });
const longDayLabel = (date) => date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });

const periodLabel = computed(() => {
    const from = new Date(props.window.from);
    const to = new Date(props.window.to);

    if (props.view === 'week') {
        return `${from.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })} – ${to.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}`;
    }

    return anchor.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
});

// --- Navigation --------------------------------------------------------

const go = (params) => router.get(route('planner.index'), { ...props.filters, ...params }, {
    preserveState: true,
    preserveScroll: true,
});

const setView = (view) => go({ view, from: props.filters.from });

const shiftPeriod = (direction) => {
    const date = new Date(anchor.value);

    if (props.view === 'week') {
        date.setDate(date.getDate() + direction * 7);
    } else if (props.view === 'agenda') {
        date.setDate(date.getDate() + direction * 14);
    } else {
        date.setMonth(date.getMonth() + direction);
    }

    go({ view: props.view, from: toKey(date) });
};

const today = () => go({ view: props.view, from: toKey(new Date()) });

// --- Drag and drop -----------------------------------------------------

const onDragStart = (post) => { dragging.value = post; };
const onDragEnd = () => { dragging.value = null; dropTarget.value = null; };

const onDrop = (date) => {
    const post = dragging.value;
    dropTarget.value = null;
    dragging.value = null;

    if (!post) return;

    // Keep the original time of day; dragging a card moves the *date*.
    const target = new Date(date);
    const original = post.scheduled_at ? new Date(post.scheduled_at) : new Date();
    target.setHours(original.getHours(), original.getMinutes(), 0, 0);

    // No optimistic mutation here: the server decides the resulting status
    // (a drop can move a draft to scheduled), and a partial reload is fast
    // enough that a rollback path would be more risk than it is worth.
    router.patch(route('planner.posts.reschedule', post.id), { scheduled_at: target.toISOString() }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => router.reload({ only: ['posts'] }),
    });
};

// --- Bulk actions ------------------------------------------------------

const toggleSelect = (id) => {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((existing) => existing !== id)
        : [...selected.value, id];
};

const clearSelection = () => { selected.value = []; };

const bulk = (action, extra = {}) => {
    if (selected.value.length === 0) return;

    if (action === 'delete' && !window.confirm(`Delete ${selected.value.length} post(s)? This cannot be undone.`)) {
        return;
    }

    router.post(route('planner.bulk'), { action, post_ids: selected.value, ...extra }, {
        preserveScroll: true,
        onSuccess: () => { clearSelection(); router.reload({ only: ['posts'] }); },
    });
};

const bulkShift = (minutes) => bulk('reschedule', { shift_minutes: minutes, scheduled_at: new Date().toISOString() });
</script>

<template>
    <AppLayout title="Calendar">
        <div class="flex flex-col gap-5">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="sc-title">Calendar</h1>
                    <p class="sc-sub">Plan, review and publish across every {{ one('workspace').toLowerCase() }}.</p>
                </div>

                <Link :href="route('posts.create')" class="sc-btn sc-btn-primary">
                    <SIcon name="plus" :size="15" :stroke-width="2.2" /> New post
                </Link>
            </div>

            <!-- View switcher + period navigation -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5" role="tablist" aria-label="Calendar view">
                    <button
                        v-for="option in VIEWS"
                        :key="option.key"
                        type="button"
                        role="tab"
                        :aria-selected="view === option.key"
                        class="sc-pill flex items-center gap-1.5"
                        :class="view === option.key ? 'sc-pill-on' : ''"
                        @click="setView(option.key)"
                    >
                        <SIcon :name="option.icon" :size="14" />
                        {{ option.label }}
                    </button>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="shiftPeriod(-1)">
                        <SIcon name="chevronLeft" :size="15" label="Previous period" />
                    </button>
                    <span class="min-w-[150px] text-center text-[14px] font-bold text-t1">{{ periodLabel }}</span>
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="shiftPeriod(1)">
                        <SIcon name="chevronRight" :size="15" label="Next period" />
                    </button>
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost" @click="today">Today</button>
                </div>
            </div>

            <FilterBar
                v-model="filterState"
                :facets="facets"
                route-name="planner.index"
                :preserve="{ view, from: filters.from }"
                saved-views-key="planner"
            />

            <!-- Bulk action bar: only present when something is selected. -->
            <div
                v-if="selected.length"
                class="sticky top-[68px] z-20 flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2.5"
                style="background: var(--card-solid); border-color: var(--sc-mid)"
                role="region"
                aria-label="Bulk actions"
            >
                <span class="text-[13.5px] font-bold text-t1">{{ selected.length }} selected</span>
                <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="bulkShift(1440)">
                    <SIcon name="chevronRight" :size="13" /> Push 1 day
                </button>
                <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="bulkShift(10080)">
                    <SIcon name="chevronRight" :size="13" /> Push 1 week
                </button>
                <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="bulk('submit')">
                    <SIcon name="checkSquare" :size="13" /> Submit for approval
                </button>
                <button type="button" class="sc-btn sc-btn-sm sc-btn-success" @click="bulk('approve', { decision: 'approved' })">
                    <SIcon name="check" :size="13" /> Approve
                </button>
                <button type="button" class="sc-btn sc-btn-sm sc-btn-danger" @click="bulk('delete')">
                    <SIcon name="trash" :size="13" /> Delete
                </button>
                <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost ml-auto" @click="clearSelection">
                    Clear selection
                </button>
            </div>

            <!-- Unscheduled tray: drafts have no date, so they would otherwise
                 be invisible on a date-driven calendar. -->
            <section v-if="unscheduled.length" class="sc-card p-3">
                <h2 class="mb-2 text-[12px] font-bold text-t4">Unscheduled ({{ unscheduled.length }})</h2>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <div v-for="post in unscheduled" :key="post.id" class="w-[220px] shrink-0">
                        <PostCard
                            :post="post"
                            selectable
                            :selected="selected.includes(post.id)"
                            @open="openPostId = $event"
                            @toggle-select="toggleSelect"
                            @dragstart="onDragStart"
                            @dragend="onDragEnd"
                        />
                    </div>
                </div>
            </section>

            <!-- MONTH -->
            <div v-if="view === 'month'" class="overflow-x-auto">
                <div class="grid min-w-[820px] grid-cols-7 gap-px rounded-xl border p-px" style="border-color: var(--line); background: var(--line)">
                    <div
                        v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                        :key="day"
                        class="px-2 py-2 text-center text-[12px] font-bold text-t4"
                        style="background: var(--bg2)"
                    >{{ day }}</div>

                    <div
                        v-for="cell in monthCells"
                        :key="cell.toISOString()"
                        class="min-h-[124px] p-1.5"
                        :class="dropTarget === toKey(cell) ? 'sc-drop-target' : ''"
                        :style="{ background: inWindowMonth(cell) ? 'var(--bg2)' : 'var(--s1)' }"
                        @dragover.prevent="dropTarget = toKey(cell)"
                        @dragleave="dropTarget === toKey(cell) && (dropTarget = null)"
                        @drop.prevent="onDrop(cell)"
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <span
                                class="text-[12.5px] font-bold"
                                :style="{ color: isToday(cell) ? 'var(--sc-accent-text)' : inWindowMonth(cell) ? 'var(--t2)' : 'var(--t4)' }"
                            >
                                {{ cell.getDate() }}<span v-if="isToday(cell)" class="sr-only"> (today)</span>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <PostCard
                                v-for="post in (byDay[toKey(cell)] ?? [])"
                                :key="post.id"
                                :post="post"
                                compact
                                selectable
                                :selected="selected.includes(post.id)"
                                @open="openPostId = $event"
                                @toggle-select="toggleSelect"
                                @dragstart="onDragStart"
                                @dragend="onDragEnd"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- WEEK -->
            <div v-else-if="view === 'week'" class="overflow-x-auto">
                <div class="grid min-w-[900px] grid-cols-7 gap-2">
                    <div
                        v-for="cell in weekCells"
                        :key="cell.toISOString()"
                        class="sc-card min-h-[420px] p-2"
                        :class="dropTarget === toKey(cell) ? 'sc-drop-target' : ''"
                        @dragover.prevent="dropTarget = toKey(cell)"
                        @dragleave="dropTarget === toKey(cell) && (dropTarget = null)"
                        @drop.prevent="onDrop(cell)"
                    >
                        <h2 class="mb-2 text-[13px] font-bold" :style="{ color: isToday(cell) ? 'var(--sc-accent-text)' : 'var(--t2)' }">
                            {{ dayLabel(cell) }}
                        </h2>
                        <div class="flex flex-col gap-2">
                            <PostCard
                                v-for="post in (byDay[toKey(cell)] ?? [])"
                                :key="post.id"
                                :post="post"
                                selectable
                                :selected="selected.includes(post.id)"
                                @open="openPostId = $event"
                                @toggle-select="toggleSelect"
                                @dragstart="onDragStart"
                                @dragend="onDragEnd"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- AGENDA -->
            <div v-else-if="view === 'agenda'" class="flex flex-col gap-4">
                <section v-for="day in agendaDays" :key="day.key">
                    <h2 class="mb-2 text-[13.5px] font-bold text-t2">{{ longDayLabel(day.date) }}</h2>
                    <div class="flex flex-col gap-2">
                        <PostCard
                            v-for="post in day.posts"
                            :key="post.id"
                            :post="post"
                            :draggable="false"
                            selectable
                            :selected="selected.includes(post.id)"
                            @open="openPostId = $event"
                            @toggle-select="toggleSelect"
                        />
                    </div>
                </section>
            </div>

            <!-- LIST -->
            <div v-else-if="view === 'list'" class="sc-card overflow-hidden">
                <div v-for="post in posts" :key="post.id" class="sc-row p-2.5">
                    <PostCard
                        :post="post"
                        :draggable="false"
                        selectable
                        :selected="selected.includes(post.id)"
                        @open="openPostId = $event"
                        @toggle-select="toggleSelect"
                    />
                </div>
            </div>

            <!-- GRID PREVIEW (Instagram-style feed planning) -->
            <div v-else-if="view === 'grid'" class="grid grid-cols-3 gap-1 sm:max-w-[540px]">
                <button
                    v-for="post in posts"
                    :key="post.id"
                    type="button"
                    class="relative aspect-square overflow-hidden rounded-md border"
                    style="border-color: var(--line)"
                    @click="openPostId = post.id"
                >
                    <img v-if="post.thumbnail" :src="post.thumbnail" :alt="post.title || 'Post preview'" class="size-full object-cover" />
                    <span v-else class="flex size-full items-center justify-center p-2 text-[12px] leading-tight text-t3"
                          style="background: var(--s2)">
                        {{ post.excerpt }}
                    </span>
                </button>
            </div>

            <EmptyState
                v-if="posts.length === 0"
                title="Nothing scheduled in this period"
                :description="`Create a post, or widen the filters to see more of your ${many('workspace').toLowerCase()}.`"
            >
                <Link :href="route('posts.create')" class="sc-btn sc-btn-primary sc-btn-sm">
                    <SIcon name="plus" :size="14" /> New post
                </Link>
            </EmptyState>
        </div>

        <PostDrawer
            :post-id="openPostId"
            @close="openPostId = null"
            @changed="router.reload({ only: ['posts'] })"
        />
    </AppLayout>
</template>
