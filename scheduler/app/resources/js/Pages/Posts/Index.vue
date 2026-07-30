<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import PostRow from '@/Pages/Posts/Partials/PostRow.vue';

const props = defineProps({
    posts: Array,
    workspaceMembers: Object,
});

const submit = (id) => router.post(route('posts.submit', id), {}, { preserveScroll: true });

const decide = (id, decision, comment = null) => router.post(
    route('posts.decision', id),
    comment ? { decision, comment } : { decision },
    { preserveScroll: true },
);

// A week grid for planning, a list for triage.
const view = ref('week');

// --- Filters ---------------------------------------------------------------
const workspaceFilter = ref('all');
const statusFilter = ref('all');

const workspaces = computed(() => {
    const seen = new Map();
    for (const post of props.posts) {
        if (post.workspace && !seen.has(post.workspace.id)) {
            seen.set(post.workspace.id, post.workspace);
        }
    }
    return [...seen.values()];
});

const STATUS_FILTERS = [
    { id: 'all', label: 'Any status' },
    { id: 'review', label: 'Needs approval' },
    { id: 'scheduled', label: 'Scheduled' },
    { id: 'problem', label: 'Failed' },
];

const visiblePosts = computed(() => props.posts.filter((post) => {
    if (workspaceFilter.value !== 'all' && post.workspace?.id !== workspaceFilter.value) return false;

    if (statusFilter.value === 'review') return post.status === 'pending_approval';
    if (statusFilter.value === 'scheduled') return post.status === 'scheduled';
    if (statusFilter.value === 'problem') return ['failed', 'partially_failed'].includes(post.status);

    return true;
}));

// --- Week grid -------------------------------------------------------------
// Anchored on an offset from the current week so the arrows page backwards
// through history and forwards into the queue.
const weekOffset = ref(0);

const startOfWeek = computed(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    // Monday-first, the way a posting week is actually planned.
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7) + weekOffset.value * 7);
    return d;
});

const days = computed(() => Array.from({ length: 7 }, (_, i) => {
    const date = new Date(startOfWeek.value);
    date.setDate(date.getDate() + i);

    return {
        key: date.toDateString(),
        dow: date.toLocaleDateString(undefined, { weekday: 'short' }),
        num: date.getDate(),
        isToday: date.toDateString() === new Date().toDateString(),
    };
}));

const weekLabel = computed(() => {
    const end = new Date(startOfWeek.value);
    end.setDate(end.getDate() + 6);

    // Month on both ends so the range reads the same in every locale.
    const format = { day: 'numeric', month: 'short' };

    return `Week of ${startOfWeek.value.toLocaleDateString(undefined, format)} – `
        + `${end.toLocaleDateString(undefined, format)}`;
});

// Posts bucketed by calendar day. Unscheduled drafts have no cell, so they get
// their own tray under the grid instead of vanishing.
const byDay = computed(() => {
    const buckets = {};
    for (const day of days.value) buckets[day.key] = [];

    for (const post of visiblePosts.value) {
        if (!post.scheduled_at) continue;
        const key = new Date(post.scheduled_at).toDateString();
        if (buckets[key]) buckets[key].push(post);
    }

    for (const key of Object.keys(buckets)) {
        buckets[key].sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at));
    }

    return buckets;
});

const unscheduled = computed(() => visiblePosts.value.filter((p) => !p.scheduled_at));

const timeOf = (post) => (post.scheduled_at
    ? new Date(post.scheduled_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : '—');

// --- Inline panels (request changes + comments) -----------------------------
const changesOpen = reactive({});
const changesComment = reactive({});

const toggleChanges = (id) => {
    changesOpen[id] = !changesOpen[id];
    if (changesOpen[id] && !(id in changesComment)) changesComment[id] = '';
};

const sendChanges = (id) => {
    decide(id, 'request_changes', changesComment[id] || null);
    changesOpen[id] = false;
};

const commentsOpen = reactive({});
const commentDrafts = reactive({});

const membersFor = (post) => props.workspaceMembers?.[post.workspace_id] ?? [];

const toggleComments = (id) => {
    commentsOpen[id] = !commentsOpen[id];
    if (commentsOpen[id] && !(id in commentDrafts)) commentDrafts[id] = { body: '', mentions: [] };
};

const submitComment = (post) => {
    const draft = commentDrafts[post.id];
    if (!draft?.body?.trim()) return;

    router.post(route('posts.comments.store', post.id), {
        body: draft.body,
        mentions: draft.mentions,
    }, {
        preserveScroll: true,
        onSuccess: () => { commentDrafts[post.id] = { body: '', mentions: [] }; },
    });
};

const memberName = (post, userId) => membersFor(post).find((m) => m.id === userId)?.name ?? '—';
</script>

<template>
    <AppLayout title="Calendar">
        <PageHeader eyebrow="Publishing schedule">
            <template #title>{{ view === 'week' ? weekLabel : 'All posts' }}</template>
            <template #subtitle>
                Every client's queue in one grid — drafts, approvals, and everything already claimed
                by the dispatcher.
            </template>
            <template #actions>
                <div class="flex items-center rounded-full p-1" style="background: var(--s2); border: 1px solid var(--line2)">
                    <button
                        v-for="mode in ['week', 'list']"
                        :key="mode"
                        type="button"
                        class="rounded-full px-4 py-1.5 text-[12.5px] font-bold capitalize transition-colors"
                        :style="view === mode ? 'background: var(--sc-soft); color: var(--t1)' : 'color: var(--t3)'"
                        @click="view = mode"
                    >{{ mode }}</button>
                </div>
                <SButton variant="primary" :href="route('posts.create')">+ Compose</SButton>
            </template>
        </PageHeader>

        <!-- Filter rail -->
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <span class="sc-eyebrow mr-1">Filter</span>

            <button class="sc-pill" :class="{ 'sc-pill-on': workspaceFilter === 'all' }" @click="workspaceFilter = 'all'">
                All clients
            </button>
            <button
                v-for="w in workspaces"
                :key="w.id"
                class="sc-pill"
                :class="{ 'sc-pill-on': workspaceFilter === w.id }"
                @click="workspaceFilter = w.id"
            >{{ w.name }}</button>

            <span class="mx-1 h-5 w-px" style="background: var(--line2)"></span>

            <button
                v-for="s in STATUS_FILTERS"
                :key="s.id"
                class="sc-pill"
                :class="{ 'sc-pill-on': statusFilter === s.id }"
                @click="statusFilter = s.id"
            >{{ s.label }}</button>
        </div>

        <!-- ── Week grid ────────────────────────────────────────────────── -->
        <template v-if="view === 'week'">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <SButton size="sm" @click="weekOffset--">←</SButton>
                    <SButton size="sm" @click="weekOffset = 0">This week</SButton>
                    <SButton size="sm" @click="weekOffset++">→</SButton>
                </div>
                <p class="text-[12px] text-t4">{{ visiblePosts.length }} post(s) in view</p>
            </div>

            <div class="sc-scroll -mx-1 overflow-x-auto px-1 pb-2">
                <div class="grid min-w-[900px] grid-cols-7 gap-2.5">
                    <div v-for="day in days" :key="day.key" class="flex flex-col gap-2.5">
                        <div
                            class="rounded-xl border px-3 py-2.5 text-center"
                            :style="day.isToday
                                ? 'background: var(--sc-soft); border-color: var(--sc-mid)'
                                : 'background: var(--s2); border-color: var(--line)'"
                        >
                            <p
                                class="text-[10.5px] font-bold uppercase tracking-[0.1em]"
                                :style="day.isToday ? 'color: var(--sc-accent-text)' : 'color: var(--t3)'"
                            >{{ day.dow }}</p>
                            <p class="mt-0.5 text-[19px] font-extrabold leading-none text-t1">{{ day.num }}</p>
                        </div>

                        <div class="sc-inset flex min-h-[220px] flex-col gap-2 p-2">
                            <button
                                v-for="post in byDay[day.key]"
                                :key="post.id"
                                type="button"
                                class="sc-card-solid w-full p-2.5 text-left shadow-card transition-transform duration-150 hover:-translate-y-0.5"
                                :style="post.status === 'pending_approval' ? 'border-color: rgba(245,158,11,0.35)' : ''"
                                @click="view = 'list'"
                            >
                                <p class="mb-1 text-[10.5px] font-extrabold text-t3">{{ timeOf(post) }}</p>
                                <p class="line-clamp-3 text-[12px] leading-snug text-t2">
                                    {{ post.content || '(no content)' }}
                                </p>
                                <p class="mt-1.5 truncate text-[10.5px] text-t4">{{ post.workspace?.name }}</p>
                            </button>

                            <p v-if="!byDay[day.key]?.length" class="px-1 py-3 text-center text-[11px] text-t4">—</p>
                        </div>
                    </div>
                </div>
            </div>

            <SCard
                v-if="unscheduled.length"
                class="mt-4"
                flush
                title="Unscheduled drafts"
                subtitle="No time set yet — schedule them or drop them in the queue."
            >
                <ul>
                    <li v-for="post in unscheduled" :key="post.id" class="sc-row flex items-center gap-4 px-5 py-3">
                        <p class="min-w-0 flex-1 truncate text-[13.5px] text-t2">{{ post.content || '(no content)' }}</p>
                        <span class="shrink-0 text-[11.5px] text-t4">{{ post.workspace?.name }}</span>
                        <StatusBadge :status="post.status" />
                    </li>
                </ul>
            </SCard>
        </template>

        <!-- ── List view ────────────────────────────────────────────────── -->
        <SCard v-else flush>
            <EmptyState
                v-if="visiblePosts.length === 0"
                icon="pencil"
                title="Nothing here yet"
                description="Compose your first post and it will show up on the calendar."
            >
                <SButton variant="primary" :href="route('posts.create')">Compose a post →</SButton>
            </EmptyState>

            <ul v-else>
                <li v-for="post in visiblePosts" :key="post.id" class="sc-row p-5">
                    <PostRow
                        :post="post"
                        :members="membersFor(post)"
                        :changes-open="!!changesOpen[post.id]"
                        :comments-open="!!commentsOpen[post.id]"
                        :changes-comment="changesComment[post.id] ?? ''"
                        :comment-draft="commentDrafts[post.id]"
                        :resolve-member="(uid) => memberName(post, uid)"
                        @submit="submit(post.id)"
                        @approve="decide(post.id, 'approve')"
                        @reject="decide(post.id, 'reject')"
                        @toggle-changes="toggleChanges(post.id)"
                        @update:changes-comment="changesComment[post.id] = $event"
                        @send-changes="sendChanges(post.id)"
                        @toggle-comments="toggleComments(post.id)"
                        @send-comment="submitComment(post)"
                    />
                </li>
            </ul>
        </SCard>
    </AppLayout>
</template>
