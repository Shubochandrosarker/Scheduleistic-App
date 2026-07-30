<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
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

const lastChecked = (value) => (value
    ? `Last checked ${new Date(value).toLocaleString([], {
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
    })}`
    : 'Never checked — the poller picks it up on its next run');
</script>

<template>
    <AppLayout :title="`Feeds — ${workspace.name}`">
        <PageHeader eyebrow="Content sources">
            <template #title>RSS &amp; WordPress feeds</template>
            <template #subtitle>
                New articles are polled on a schedule and drafted as social posts in
                {{ workspace.name }} — de-duplicated per article, so nothing posts twice.
            </template>
            <template #actions>
                <SButton :href="route('workspaces.index')">← All clients</SButton>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
            <SCard title="Add a feed">
                <form class="space-y-4" @submit.prevent="create">
                    <div>
                        <label class="sc-label" for="url">Feed URL</label>
                        <input
                            id="url"
                            v-model="form.url"
                            type="url"
                            class="sc-input"
                            placeholder="https://example.com/feed"
                            required
                        />
                        <InputError :message="form.errors.url" class="mt-1.5" />
                    </div>

                    <div>
                        <label class="sc-label" for="name">Label (optional)</label>
                        <input id="name" v-model="form.name" type="text" class="sc-input" placeholder="Blog" />
                        <InputError :message="form.errors.name" class="mt-1.5" />
                    </div>

                    <SButton type="submit" variant="primary" class="w-full" :disabled="form.processing">
                        Add feed
                    </SButton>
                </form>
            </SCard>

            <SCard flush title="Connected feeds" :subtitle="`${feeds.length} source(s) for this client.`">
                <EmptyState
                    v-if="feeds.length === 0"
                    icon="plug"
                    title="No feeds yet"
                    description="Add one and new articles start drafting themselves into this workspace."
                />

                <ul v-else>
                    <li v-for="feed in feeds" :key="feed.id" class="sc-row flex items-start gap-4 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13.5px] font-bold text-t1">{{ feed.name || feed.url }}</p>
                            <p class="truncate text-[12px] text-t3">{{ feed.url }}</p>
                            <p class="mt-1 text-[11.5px] text-t4">{{ lastChecked(feed.last_polled_at) }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2.5">
                            <SBadge :tone="feed.active ? 'ok' : 'neutral'">
                                {{ feed.active ? 'Active' : 'Paused' }}
                            </SBadge>
                            <button
                                type="button"
                                class="text-[11.5px] font-bold transition-opacity hover:opacity-70"
                                style="color: var(--bad)"
                                @click="destroy(feed.id)"
                            >Remove</button>
                        </div>
                    </li>
                </ul>
            </SCard>
        </div>
    </AppLayout>
</template>
