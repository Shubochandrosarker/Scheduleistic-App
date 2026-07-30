<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import NetworkChip from '@/Components/UI/NetworkChip.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import { network } from '@/networks';

const props = defineProps({
    workspaces: Array,
});

const form = useForm({
    workspace_id: props.workspaces[0]?.id ?? null,
    content: '',
    channel_ids: [],
    scheduled_at: '',
    use_queue: false,
    recurring_rule: '',
});

const selectedWorkspace = computed(
    () => props.workspaces.find((w) => w.id === form.workspace_id),
);

const channels = computed(() => selectedWorkspace.value?.channels ?? []);

const selectedChannels = computed(
    () => channels.value.filter((c) => form.channel_ids.includes(c.id)),
);

// The tightest caption cap among the selected networks — that's the one that
// will actually truncate, so it's the one worth counting against.
const limit = computed(() => {
    if (selectedChannels.value.length === 0) return null;
    return Math.min(...selectedChannels.value.map((c) => network(c.provider).limit));
});

const characters = computed(() => form.content.length);
const overLimit = computed(() => limit.value !== null && characters.value > limit.value);

const toggleChannel = (id) => {
    const index = form.channel_ids.indexOf(id);
    if (index === -1) form.channel_ids.push(id);
    else form.channel_ids.splice(index, 1);
};

// Switching client resets the targets — channel ids belong to one workspace.
const selectWorkspace = (id) => {
    form.workspace_id = id;
    form.channel_ids = [];
};

// --- Scheduling mode -------------------------------------------------------
// Three modes map onto two form fields: `use_queue` and `scheduled_at`.
const mode = computed({
    get() {
        if (form.use_queue) return 'queue';
        return form.scheduled_at ? 'exact' : 'now';
    },
    set(value) {
        form.use_queue = value === 'queue';
        if (value !== 'exact') form.scheduled_at = '';
    },
});

const MODES = [
    { id: 'now', label: 'Save as draft', hint: 'Kept off the schedule until you submit or set a time.' },
    { id: 'queue', label: 'Add to queue', hint: 'Lands in the next open time slot for this workspace.' },
    { id: 'exact', label: 'Exact time', hint: 'Dispatched at the minute you pick, in the workspace timezone.' },
];

const modeHint = computed(() => MODES.find((m) => m.id === mode.value)?.hint ?? '');

// --- AI assistant ----------------------------------------------------------
const aiBusy = ref(false);
const aiTopic = ref('');
const aiError = ref('');

const generate = async () => {
    const providers = selectedChannels.value.map((c) => c.provider);

    if (!aiTopic.value || providers.length === 0) return;

    aiBusy.value = true;
    aiError.value = '';

    try {
        const { data } = await window.axios.post(route('ai.generate'), {
            topic: aiTopic.value,
            providers: [...new Set(providers)],
        });
        // Use the first generated caption as the base content.
        form.content = Object.values(data.captions)[0] ?? form.content;
    } catch (e) {
        aiError.value = e?.response?.data?.message
            ?? 'The assistant is unavailable right now. Write it yourself and try again later.';
    } finally {
        aiBusy.value = false;
    }
};

const submit = () => form.post(route('posts.store'));

const ctaLabel = computed(() => {
    if (mode.value === 'queue') return 'Add to queue →';
    if (mode.value === 'exact') return 'Schedule post →';
    return 'Save draft';
});
</script>

<template>
    <AppLayout title="Compose">
        <PageHeader eyebrow="Composer">
            <template #title>Write once. Ship everywhere.</template>
            <template #subtitle>
                One body of copy fans out to every target you tick — each network publishes
                independently, so one failure never blocks the rest.
            </template>
            <template #actions>
                <SButton :href="route('posts.index')">Cancel</SButton>
                <SButton
                    variant="primary"
                    :disabled="form.processing || form.channel_ids.length === 0 || !form.content.trim()"
                    @click="submit"
                >{{ ctaLabel }}</SButton>
            </template>
        </PageHeader>

        <InputError :message="form.errors.plan" class="mb-4" />

        <form class="grid gap-4 lg:grid-cols-[1.35fr_1fr]" @submit.prevent="submit">
            <!-- ── Left: copy, media intent, schedule ───────────────────── -->
            <div class="space-y-4">
                <SCard flush>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                        <span class="sc-eyebrow">Copy</span>
                        <span
                            class="text-[12px] font-bold"
                            :style="overLimit ? 'color: var(--bad)' : 'color: var(--t3)'"
                        >
                            {{ characters }}<template v-if="limit"> / {{ limit.toLocaleString() }}</template>
                        </span>
                    </div>

                    <div class="px-5 pb-5">
                        <textarea
                            v-model="form.content"
                            rows="9"
                            class="sc-input !text-[14px] !leading-relaxed"
                            placeholder="What do you want to share?"
                        ></textarea>
                        <InputError :message="form.errors.content" class="mt-2" />

                        <p v-if="overLimit" class="mt-2 text-[12px] font-semibold" style="color: var(--bad)">
                            Over the tightest limit among your targets — {{ limit.toLocaleString() }} characters.
                        </p>
                    </div>

                    <!-- AI assistant -->
                    <div class="border-t px-5 py-4" style="border-color: var(--line); background: var(--s1)">
                        <label class="sc-label">✨ AI assistant — describe a topic</label>
                        <div class="flex flex-wrap gap-2.5">
                            <input
                                v-model="aiTopic"
                                type="text"
                                class="sc-input min-w-[200px] flex-1"
                                placeholder="e.g. 5 WordPress speed tips"
                            />
                            <SButton
                                variant="secondary"
                                :disabled="aiBusy || form.channel_ids.length === 0 || !aiTopic"
                                @click="generate"
                            >{{ aiBusy ? 'Generating…' : 'Generate' }}</SButton>
                        </div>
                        <p class="mt-2 text-[11.5px] text-t4">
                            Writes in this client's brand voice for the networks you've targeted.
                        </p>
                        <p v-if="aiError" class="mt-2 text-[12px] font-semibold" style="color: var(--bad)">
                            {{ aiError }}
                        </p>
                    </div>
                </SCard>

                <!-- Schedule -->
                <SCard title="Schedule">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="m in MODES"
                            :key="m.id"
                            type="button"
                            class="sc-pill"
                            :class="{ 'sc-pill-on': mode === m.id }"
                            @click="mode = m.id"
                        >{{ m.label }}</button>
                    </div>

                    <div v-if="mode === 'exact'" class="mt-4">
                        <label class="sc-label" for="scheduled_at">Publish at</label>
                        <input
                            id="scheduled_at"
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="sc-input"
                        />
                        <InputError :message="form.errors.scheduled_at" class="mt-2" />
                    </div>

                    <p class="mt-3 text-[12px] text-t4">{{ modeHint }}</p>

                    <div class="mt-5 flex flex-wrap items-center gap-3 border-t pt-4" style="border-color: var(--line)">
                        <label class="sc-label !mb-0" for="recurring">Repeat</label>
                        <select id="recurring" v-model="form.recurring_rule" class="sc-input !w-auto">
                            <option value="">Never</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </SCard>
            </div>

            <!-- ── Right: client + targets ──────────────────────────────── -->
            <div class="space-y-4">
                <SCard title="Client workspace" subtitle="Content and channels stay sealed inside it.">
                    <EmptyState
                        v-if="workspaces.length === 0"
                        icon="layers"
                        title="No workspaces yet"
                        description="Add a client workspace before composing."
                    >
                        <SButton variant="primary" :href="route('workspaces.index')">Add a client →</SButton>
                    </EmptyState>

                    <select
                        v-else
                        :value="form.workspace_id"
                        class="sc-input"
                        @change="selectWorkspace(Number($event.target.value))"
                    >
                        <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <InputError :message="form.errors.workspace_id" class="mt-2" />
                </SCard>

                <SCard flush>
                    <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                        <span class="sc-eyebrow">Targets</span>
                        <SBadge :tone="form.channel_ids.length ? 'accent' : 'neutral'">
                            {{ form.channel_ids.length }} selected
                        </SBadge>
                    </div>

                    <div class="px-5 pb-5">
                        <EmptyState
                            v-if="channels.length === 0"
                            icon="inbox"
                            title="No channels connected"
                            description="Connect a social account to this workspace and it shows up here."
                        >
                            <SButton
                                v-if="selectedWorkspace"
                                variant="primary"
                                :href="route('workspaces.channels.index', selectedWorkspace.id)"
                            >Connect a channel →</SButton>
                        </EmptyState>

                        <div v-else class="space-y-2">
                            <button
                                v-for="c in channels"
                                :key="c.id"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors"
                                :style="form.channel_ids.includes(c.id)
                                    ? 'background: var(--sc-soft); border-color: var(--sc-mid)'
                                    : 'background: var(--s1); border-color: var(--line)'"
                                @click="toggleChannel(c.id)"
                            >
                                <NetworkChip :provider="c.provider" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] font-bold text-t1">{{ c.name }}</span>
                                    <span class="block text-[11.5px] text-t4">{{ network(c.provider).label }}</span>
                                </span>
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded-md text-[11px] font-extrabold"
                                    :style="form.channel_ids.includes(c.id)
                                        ? 'background: var(--sc-accent); color: var(--on-acc); border: 1px solid transparent'
                                        : 'background: transparent; color: transparent; border: 1px solid var(--hover)'"
                                >✓</span>
                            </button>
                        </div>

                        <InputError :message="form.errors.channel_ids" class="mt-2" />
                    </div>
                </SCard>

                <!-- Live preview -->
                <SCard v-if="form.content.trim()" flush>
                    <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                        <span class="sc-eyebrow">Live preview</span>
                        <SBadge v-if="selectedChannels[0]" tone="accent">
                            {{ network(selectedChannels[0].provider).label }}
                        </SBadge>
                    </div>

                    <div class="mx-5 mb-5 rounded-xl border p-4" style="background: var(--s1); border-color: var(--line)">
                        <div class="mb-3 flex items-center gap-2.5">
                            <span
                                class="size-8 shrink-0 rounded-full"
                                style="background: linear-gradient(135deg, var(--sc-accent), var(--sc-accent-2))"
                            ></span>
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-bold text-t1">
                                    {{ selectedChannels[0]?.name ?? selectedWorkspace?.name ?? 'Your brand' }}
                                </span>
                                <span class="block text-[11px] text-t4">Preview · not yet published</span>
                            </span>
                        </div>

                        <p class="whitespace-pre-line text-[13.5px] leading-relaxed text-t2">{{ form.content }}</p>
                    </div>
                </SCard>
            </div>
        </form>
    </AppLayout>
</template>
