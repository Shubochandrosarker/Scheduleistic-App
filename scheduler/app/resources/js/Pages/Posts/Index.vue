<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    posts: Array,
});

const submit = (id) => router.post(route('posts.submit', id), {}, { preserveScroll: true });

const decide = (id, decision) => router.post(
    route('posts.decision', id),
    { decision },
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
                    <div v-for="post in posts" :key="post.id" class="p-6 flex items-start justify-between gap-4">
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
                </div>
            </div>
        </div>
    </AppLayout>
</template>
