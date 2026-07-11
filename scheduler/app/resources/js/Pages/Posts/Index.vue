<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const statusColor = (status) => ({
    draft: 'bg-gray-100 text-gray-700',
    scheduled: 'bg-blue-100 text-blue-700',
    publishing: 'bg-amber-100 text-amber-700',
    published: 'bg-green-100 text-green-700',
    partially_failed: 'bg-orange-100 text-orange-700',
    failed: 'bg-red-100 text-red-700',
}[status] ?? 'bg-gray-100 text-gray-700');

// --- Request changes: an inline comment box per post, toggled open. ---
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

// --- Comments: an inline thread per post, toggled open. ---
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
    <AppLayout title="Posts">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Scheduled & published posts</h2>
                <Link :href="route('posts.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                    + Compose
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow sm:rounded-lg divide-y">
                    <div v-if="posts.length === 0" class="p-6 text-gray-500">
                        Nothing here yet. <Link :href="route('posts.create')" class="text-indigo-600 hover:underline">Compose your first post.</Link>
                    </div>
                    <div v-for="post in posts" :key="post.id" class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-gray-900 truncate">{{ post.content || '(no content)' }}</p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ post.workspace?.name }} ·
                                    {{ post.scheduled_at ? new Date(post.scheduled_at).toLocaleString() : 'unscheduled' }} ·
                                    {{ post.targets?.length ?? 0 }} target(s)
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <button v-if="post.status === 'draft'" @click="submit(post.id)"
                                    class="text-xs px-2.5 py-1 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                                    Submit for approval
                                </button>
                                <template v-if="post.status === 'pending_approval'">
                                    <button @click="decide(post.id, 'approve')"
                                        class="text-xs px-2.5 py-1 rounded-md bg-green-600 text-white hover:bg-green-700">
                                        Approve
                                    </button>
                                    <button @click="toggleChanges(post.id)"
                                        class="text-xs px-2.5 py-1 rounded-md bg-amber-500 text-white hover:bg-amber-600">
                                        Request changes
                                    </button>
                                    <button @click="decide(post.id, 'reject')"
                                        class="text-xs px-2.5 py-1 rounded-md bg-red-600 text-white hover:bg-red-700">
                                        Reject
                                    </button>
                                </template>
                                <span :class="statusColor(post.status)" class="px-2.5 py-1 rounded-full text-xs font-medium">
                                    {{ post.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Request-changes comment box -->
                        <div v-if="changesOpen[post.id]" class="mt-3 flex gap-2">
                            <textarea v-model="changesComment[post.id]" rows="2" placeholder="What needs to change? (optional)"
                                class="block w-full text-sm border-gray-300 rounded-md shadow-sm"></textarea>
                            <button @click="sendChanges(post.id)"
                                class="shrink-0 self-start text-xs px-3 py-2 rounded-md bg-amber-500 text-white hover:bg-amber-600">
                                Send
                            </button>
                        </div>

                        <!-- Comments -->
                        <div class="mt-2">
                            <button @click="toggleComments(post.id)" class="text-xs text-gray-500 hover:text-gray-700">
                                💬 {{ post.comments?.length ?? 0 }} comment{{ (post.comments?.length ?? 0) === 1 ? '' : 's' }}
                            </button>

                            <div v-if="commentsOpen[post.id]" class="mt-3 space-y-3 border-t pt-3">
                                <div v-for="comment in post.comments" :key="comment.id" class="text-sm">
                                    <p>
                                        <span class="font-medium text-gray-900">{{ comment.user?.name ?? 'Someone' }}</span>
                                        <span class="text-gray-400 text-xs ml-1">
                                            {{ new Date(comment.created_at).toLocaleString() }}
                                        </span>
                                    </p>
                                    <p class="text-gray-700">
                                        {{ comment.body }}
                                        <span v-for="uid in (comment.mentions ?? [])" :key="uid"
                                            class="inline-block ml-1 text-indigo-600 text-xs font-medium">
                                            @{{ memberName(post, uid) }}
                                        </span>
                                    </p>
                                </div>

                                <div v-if="commentDrafts[post.id]" class="space-y-2">
                                    <textarea v-model="commentDrafts[post.id].body" rows="2" placeholder="Add a comment…"
                                        class="block w-full text-sm border-gray-300 rounded-md shadow-sm"></textarea>
                                    <div v-if="membersFor(post).length" class="flex flex-wrap gap-2 items-center">
                                        <span class="text-xs text-gray-400">Mention:</span>
                                        <label v-for="m in membersFor(post)" :key="m.id"
                                            class="inline-flex items-center gap-1 text-xs text-gray-600">
                                            <input type="checkbox" :value="m.id" v-model="commentDrafts[post.id].mentions" />
                                            {{ m.name }}
                                        </label>
                                    </div>
                                    <div class="flex justify-end">
                                        <button @click="submitComment(post)"
                                            :disabled="!commentDrafts[post.id].body.trim()"
                                            class="text-xs px-3 py-1.5 rounded-md bg-gray-800 text-white hover:bg-gray-700 disabled:opacity-50">
                                            Comment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
