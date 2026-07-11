<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    workspace: Object,
    feeds: Array,
});

const form = useForm({
    url: '',
    name: '',
});

const create = () => form.post(route('workspaces.feeds.store', props.workspace.id), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const destroy = (id) => {
    if (confirm('Remove this feed? It will stop drafting new posts from it.')) {
        useForm({}).delete(route('workspaces.feeds.destroy', [props.workspace.id, id]), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :title="`Feeds — ${workspace.name}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                RSS / WordPress feeds — {{ workspace.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Add a feed</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        New articles from this feed are checked periodically and automatically
                        drafted as social posts in this workspace, de-duplicated by article.
                    </p>
                    <form @submit.prevent="create" class="grid gap-4 sm:grid-cols-3 items-start">
                        <div class="sm:col-span-2">
                            <InputLabel for="url" value="Feed URL" />
                            <TextInput id="url" v-model="form.url" type="url" class="mt-1 block w-full"
                                placeholder="https://example.com/feed" required />
                            <InputError :message="form.errors.url" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="name" value="Label (optional)" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="Blog" />
                        </div>
                        <PrimaryButton class="mt-1" :disabled="form.processing">Add feed</PrimaryButton>
                    </form>
                </div>

                <div class="bg-white shadow sm:rounded-lg divide-y">
                    <div v-if="feeds.length === 0" class="p-6 text-gray-500">
                        No feeds yet. Add one above to start drafting posts from new articles.
                    </div>
                    <div v-for="feed in feeds" :key="feed.id" class="p-6 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ feed.name || feed.url }}</p>
                            <p class="text-sm text-gray-500 truncate">
                                {{ feed.url }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                <span :class="feed.active ? 'text-green-600' : 'text-gray-400'">
                                    {{ feed.active ? 'Active' : 'Inactive' }}
                                </span>
                                ·
                                {{ feed.last_polled_at ? `last checked ${new Date(feed.last_polled_at).toLocaleString()}` : 'not checked yet' }}
                            </p>
                        </div>
                        <DangerButton class="shrink-0" @click="destroy(feed.id)">Remove</DangerButton>
                    </div>
                </div>

                <Link :href="route('workspaces.index')" class="text-indigo-600 hover:underline text-sm">
                    ← Back to workspaces
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
