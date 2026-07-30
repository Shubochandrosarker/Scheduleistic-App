<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SIcon from '@/Components/UI/SIcon.vue';
import { network } from '@/networks';

/**
 * The post detail drawer.
 *
 * Clicking a calendar card opens this instead of navigating away — the brief
 * is explicit that opening a post must not tear down the calendar. The drawer
 * is a proper dialog: focus moves into it, Escape closes it, and focus returns
 * to the card that opened it.
 */
const props = defineProps({
    postId: { type: [Number, null], default: null },
});

const emit = defineEmits(['close', 'changed']);

const loading = ref(false);
const error = ref(null);
const detail = ref(null);
const panel = ref(null);
const closeButton = ref(null);

let previouslyFocused = null;

const load = async (id) => {
    if (!id) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(route('planner.posts.show', id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Could not load this post (${response.status}).`);

        detail.value = await response.json();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
};

watch(() => props.postId, async (id) => {
    detail.value = null;

    if (!id) return;

    previouslyFocused = document.activeElement;
    await load(id);
    await nextTick();
    closeButton.value?.focus();
}, { immediate: true });

const close = () => {
    emit('close');
    // Return focus to whatever opened the drawer, as WCAG 2.2 requires.
    previouslyFocused?.focus?.();
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
        return;
    }

    if (event.key !== 'Tab' || !panel.value) return;

    // Keep Tab inside the dialog while it is open.
    const focusable = panel.value.querySelectorAll(
        'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );

    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));

const remove = () => {
    if (!window.confirm('Delete this post? This cannot be undone.')) return;

    router.post(route('planner.bulk'), { action: 'delete', post_ids: [props.postId] }, {
        preserveScroll: true,
        onSuccess: () => { emit('changed'); close(); },
    });
};

const decide = (decision) => {
    router.post(route('planner.bulk'), { action: 'approve', post_ids: [props.postId], decision }, {
        preserveScroll: true,
        onSuccess: () => { emit('changed'); load(props.postId); },
    });
};

const submit = () => {
    router.post(route('planner.bulk'), { action: 'submit', post_ids: [props.postId] }, {
        preserveScroll: true,
        onSuccess: () => { emit('changed'); load(props.postId); },
    });
};

const formatDate = (value) => value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : '—';
</script>

<template>
    <div v-if="postId" class="fixed inset-0 z-50 flex justify-end">
        <div class="absolute inset-0 bg-black/50" @click="close"></div>

        <div
            ref="panel"
            class="sc-scroll relative flex h-full w-full max-w-[520px] flex-col overflow-y-auto border-l"
            style="background: var(--bg2); border-color: var(--line2)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="drawer-title"
        >
            <header
                class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b px-5 py-4"
                style="background: var(--head-bg); border-color: var(--line)"
            >
                <div class="min-w-0">
                    <h2 id="drawer-title" class="truncate text-[17px] font-extrabold text-t1">
                        {{ detail?.post?.title || 'Post details' }}
                    </h2>
                    <p class="text-[12.5px] text-t3">{{ detail?.post?.workspace?.name }}</p>
                </div>

                <button ref="closeButton" type="button" class="sc-btn sc-btn-sm sc-btn-ghost" @click="close">
                    <SIcon name="close" :size="16" label="Close post details" />
                </button>
            </header>

            <div v-if="loading" class="flex flex-col gap-3 p-5">
                <div class="sc-skeleton h-4 w-1/3"></div>
                <div class="sc-skeleton h-24 w-full"></div>
                <div class="sc-skeleton h-4 w-2/3"></div>
            </div>

            <div v-else-if="error" class="m-5 rounded-xl border p-4 text-[13.5px]"
                 style="border-color: var(--bad); color: var(--bad)">
                {{ error }}
            </div>

            <div v-else-if="detail" class="flex flex-col gap-5 p-5">
                <section>
                    <h3 class="mb-1.5 text-[12px] font-bold text-t4">Caption</h3>
                    <p class="whitespace-pre-wrap rounded-xl border p-3 text-[14px] leading-relaxed text-t1"
                       style="background: var(--s1); border-color: var(--line)">{{ detail.post.content }}</p>
                </section>

                <section class="grid grid-cols-2 gap-3">
                    <div>
                        <h3 class="mb-1 text-[12px] font-bold text-t4">Scheduled</h3>
                        <p class="text-[13.5px] font-semibold text-t1">{{ formatDate(detail.post.scheduled_at) }}</p>
                        <p class="text-[12px] text-t3">{{ detail.post.timezone }}</p>
                    </div>
                    <div>
                        <h3 class="mb-1 text-[12px] font-bold text-t4">Assignee</h3>
                        <p class="text-[13.5px] font-semibold text-t1">{{ detail.post.assignee?.name || 'Unassigned' }}</p>
                    </div>
                    <div v-if="detail.post.campaign">
                        <h3 class="mb-1 text-[12px] font-bold text-t4">Campaign</h3>
                        <p class="text-[13.5px] font-semibold text-t1">{{ detail.post.campaign.name }}</p>
                    </div>
                    <div v-if="detail.post.pillar">
                        <h3 class="mb-1 text-[12px] font-bold text-t4">Content pillar</h3>
                        <p class="text-[13.5px] font-semibold text-t1">{{ detail.post.pillar.name }}</p>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-[12px] font-bold text-t4">Destinations</h3>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="target in detail.post.targets"
                            :key="target.id"
                            class="flex items-start justify-between gap-3 rounded-xl border p-2.5"
                            style="border-color: var(--line)"
                        >
                            <div class="min-w-0">
                                <p class="text-[13.5px] font-bold text-t1">
                                    {{ network(target.provider).label }}
                                </p>
                                <p class="truncate text-[12px] text-t3">{{ target.channel }}</p>
                                <p v-if="target.error" class="mt-1 text-[12px] font-semibold" style="color: var(--bad)">
                                    {{ target.error }}
                                </p>
                            </div>
                            <span class="shrink-0 text-[12px] font-bold"
                                  :style="{ color: target.status === 'failed' ? 'var(--bad)'
                                            : target.status === 'published' ? 'var(--ok)' : 'var(--t3)' }">
                                {{ target.status }}
                            </span>
                        </li>
                    </ul>
                </section>

                <section v-if="detail.post.versions?.length">
                    <h3 class="mb-2 text-[12px] font-bold text-t4">Per-network overrides</h3>
                    <div
                        v-for="version in detail.post.versions"
                        :key="version.provider"
                        class="mb-2 rounded-xl border p-2.5"
                        style="border-color: var(--line)"
                    >
                        <p class="mb-1 text-[12.5px] font-bold text-t2">{{ network(version.provider).label }}</p>
                        <p class="whitespace-pre-wrap text-[13px] text-t2">{{ version.content || '(uses the base caption)' }}</p>
                    </div>
                </section>

                <section v-if="detail.post.comments?.length">
                    <h3 class="mb-2 text-[12px] font-bold text-t4">Comments</h3>
                    <div
                        v-for="comment in detail.post.comments"
                        :key="comment.id"
                        class="mb-2 rounded-xl border p-2.5"
                        style="border-color: var(--line)"
                    >
                        <p class="text-[12px] font-bold text-t2">{{ comment.user }}</p>
                        <p class="text-[13.5px] text-t1">{{ comment.body }}</p>
                    </div>
                </section>

                <section v-if="detail.post.approvals?.length">
                    <h3 class="mb-2 text-[12px] font-bold text-t4">Approval history</h3>
                    <ol class="flex flex-col gap-1.5">
                        <li
                            v-for="approval in detail.post.approvals"
                            :key="approval.id"
                            class="flex items-center justify-between gap-2 text-[13px]"
                        >
                            <span class="font-semibold text-t2">{{ approval.state }}</span>
                            <span class="text-t3">{{ approval.decider || approval.requester }} · {{ formatDate(approval.decided_at || approval.created_at) }}</span>
                        </li>
                    </ol>
                </section>
            </div>

            <footer
                v-if="detail && !loading"
                class="sticky bottom-0 mt-auto flex flex-wrap gap-2 border-t px-5 py-4"
                style="background: var(--head-bg); border-color: var(--line)"
            >
                <button v-if="detail.can.update" type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="submit">
                    <SIcon name="checkSquare" :size="14" /> Submit for approval
                </button>
                <button v-if="detail.can.approve" type="button" class="sc-btn sc-btn-sm sc-btn-success" @click="decide('approved')">
                    <SIcon name="check" :size="14" /> Approve
                </button>
                <button v-if="detail.can.approve" type="button" class="sc-btn sc-btn-sm sc-btn-warn" @click="decide('changes_requested')">
                    <SIcon name="pencil" :size="14" /> Request changes
                </button>
                <button v-if="detail.can.delete" type="button" class="sc-btn sc-btn-sm sc-btn-danger ml-auto" @click="remove">
                    <SIcon name="trash" :size="14" /> Delete
                </button>
            </footer>
        </div>
    </div>
</template>
