<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { useTerms } from '@/composables/useCapabilities';

const props = defineProps({
    campaigns: { type: Object, required: true },
    workspaces: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { one } = useTerms();

const creating = ref(false);

const form = useForm({
    workspace_id: props.workspaces[0]?.id ?? null,
    name: '',
    goal: '',
    description: '',
    status: 'planning',
    starts_on: '',
    ends_on: '',
    target_url: '',
    color: '#4F8DFF',
    utm: { source: '', medium: '', campaign: '' },
});

const submit = () => form.post(route('campaigns.store'), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); creating.value = false; },
});

const remove = (campaign) => {
    if (!window.confirm(`Delete “${campaign.name}”? Its posts are kept and simply lose the campaign label.`)) return;

    router.delete(route('campaigns.destroy', campaign.id), { preserveScroll: true });
};

const dateRange = (campaign) => {
    if (!campaign.starts_on && !campaign.ends_on) return 'No dates set';

    const fmt = (d) => d ? new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '…';

    return `${fmt(campaign.starts_on)} – ${fmt(campaign.ends_on)}`;
};
</script>

<template>
    <AppLayout title="Campaigns">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="sc-title">Campaigns</h1>
                    <p class="sc-sub">Group posts, ideas and reporting around one push.</p>
                </div>
                <button type="button" class="sc-btn sc-btn-primary" @click="creating = !creating">
                    <SIcon name="plus" :size="15" /> New campaign
                </button>
            </div>

            <form v-if="creating" class="sc-card flex flex-col gap-3 p-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="sc-label" for="c-workspace">{{ one('workspace') }}</label>
                        <select id="c-workspace" v-model="form.workspace_id" class="sc-input" required>
                            <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                        <p v-if="form.errors.workspace_id" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.workspace_id }}</p>
                    </div>
                    <div>
                        <label class="sc-label" for="c-name">Name</label>
                        <input id="c-name" v-model="form.name" class="sc-input" required maxlength="120" />
                        <p v-if="form.errors.name" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="sc-label" for="c-goal">Goal</label>
                        <input id="c-goal" v-model="form.goal" class="sc-input" placeholder="e.g. 500 trial signups" />
                    </div>
                    <div>
                        <label class="sc-label" for="c-status">Status</label>
                        <select id="c-status" v-model="form.status" class="sc-input">
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="sc-label" for="c-start">Starts</label>
                        <input id="c-start" v-model="form.starts_on" type="date" class="sc-input" />
                    </div>
                    <div>
                        <label class="sc-label" for="c-end">Ends</label>
                        <input id="c-end" v-model="form.ends_on" type="date" class="sc-input" />
                        <p v-if="form.errors.ends_on" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.ends_on }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="sc-label" for="c-url">Target URL</label>
                        <input id="c-url" v-model="form.target_url" type="url" class="sc-input" placeholder="https://example.com/offer" />
                        <p v-if="form.errors.target_url" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.target_url }}</p>
                    </div>
                </div>

                <fieldset class="grid gap-3 sm:grid-cols-3">
                    <legend class="sc-label">UTM defaults</legend>
                    <input v-model="form.utm.source" class="sc-input" placeholder="utm_source" aria-label="UTM source" />
                    <input v-model="form.utm.medium" class="sc-input" placeholder="utm_medium" aria-label="UTM medium" />
                    <input v-model="form.utm.campaign" class="sc-input" placeholder="utm_campaign" aria-label="UTM campaign" />
                </fieldset>

                <div class="flex gap-2">
                    <button type="submit" class="sc-btn sc-btn-primary sc-btn-sm" :disabled="form.processing">Create campaign</button>
                    <button type="button" class="sc-btn sc-btn-ghost sc-btn-sm" @click="creating = false">Cancel</button>
                </div>
            </form>

            <div v-if="campaigns.data.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="campaign in campaigns.data" :key="campaign.id" class="sc-card p-4">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="truncate text-[15px] font-extrabold text-t1">{{ campaign.name }}</h2>
                            <p class="text-[12.5px] text-t3">{{ campaign.workspace?.name }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[12px] font-bold"
                              style="background: var(--s2); color: var(--t2)">{{ campaign.status }}</span>
                    </div>

                    <p v-if="campaign.goal" class="mb-2 text-[13.5px] text-t2">{{ campaign.goal }}</p>
                    <p class="mb-3 text-[12.5px] text-t3">{{ dateRange(campaign) }}</p>

                    <div class="flex items-center justify-between gap-2 border-t pt-3" style="border-color: var(--line)">
                        <p class="text-[13px] text-t2">
                            <strong class="text-t1">{{ campaign.published_posts_count }}</strong> of
                            <strong class="text-t1">{{ campaign.posts_count }}</strong> published
                        </p>
                        <div class="flex gap-1">
                            <a :href="route('planner.index', { campaign_ids: [campaign.id], view: 'list' })" class="sc-btn sc-btn-sm sc-btn-secondary">
                                <SIcon name="calendar" :size="13" /> View
                            </a>
                            <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost !px-2" @click="remove(campaign)">
                                <SIcon name="trash" :size="14" :label="`Delete ${campaign.name}`" />
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                icon="megaphone"
                title="No campaigns yet"
                description="A campaign ties posts, ideas and reporting to one objective — a launch, a promotion, an always-on stream."
            />
        </div>
    </AppLayout>
</template>
