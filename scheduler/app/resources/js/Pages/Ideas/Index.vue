<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { useTerms } from '@/composables/useCapabilities';

/**
 * The ideas pipeline. Kanban and list render the same array — a board is a
 * grouping of the list, not a different data source.
 */
const props = defineProps({
    ideas: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    workspaces: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    pillars: { type: Array, default: () => [] },
});

const { one } = useTerms();

const layout = ref('board');
const creating = ref(false);
const dragging = ref(null);

const form = useForm({
    workspace_id: props.workspaces[0]?.id ?? null,
    title: '',
    notes: '',
    stage: 'inbox',
    priority: 'normal',
    source_url: '',
    campaign_id: null,
    content_pillar_id: null,
});

const byStage = computed(() => Object.fromEntries(
    props.stages.map((stage) => [stage, props.ideas.filter((idea) => idea.stage === stage)]),
));

const submit = () => form.post(route('ideas.store'), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); creating.value = false; },
});

const moveTo = (idea, stage) => {
    if (!idea || idea.stage === stage) return;

    router.put(route('ideas.update', idea.id), { title: idea.title, stage }, { preserveScroll: true });
};

const convert = (idea) => {
    const count = Number(window.prompt('How many drafts should this idea produce?', '1'));
    if (!count || count < 1) return;

    router.post(route('ideas.convert', idea.id), { count }, { preserveScroll: true });
};

const remove = (idea) => {
    if (!window.confirm(`Delete “${idea.title}”?`)) return;

    router.delete(route('ideas.destroy', idea.id), { preserveScroll: true });
};

const priorityColor = (priority) => ({
    urgent: 'var(--bad)', high: 'var(--warn)', normal: 'var(--t3)', low: 'var(--t4)',
}[priority] ?? 'var(--t3)');
</script>

<template>
    <AppLayout title="Ideas">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="sc-title">Ideas</h1>
                    <p class="sc-sub">Capture, research and turn ideas into scheduled posts.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex gap-1" role="tablist" aria-label="Layout">
                        <button type="button" role="tab" :aria-selected="layout === 'board'"
                                class="sc-pill" :class="layout === 'board' ? 'sc-pill-on' : ''" @click="layout = 'board'">
                            <SIcon name="layers" :size="13" /> Board
                        </button>
                        <button type="button" role="tab" :aria-selected="layout === 'list'"
                                class="sc-pill" :class="layout === 'list' ? 'sc-pill-on' : ''" @click="layout = 'list'">
                            <SIcon name="menu" :size="13" /> List
                        </button>
                    </div>
                    <button type="button" class="sc-btn sc-btn-primary" @click="creating = !creating">
                        <SIcon name="plus" :size="15" /> New idea
                    </button>
                </div>
            </div>

            <form v-if="creating" class="sc-card flex flex-col gap-3 p-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="sc-label" for="i-workspace">{{ one('workspace') }}</label>
                        <select id="i-workspace" v-model="form.workspace_id" class="sc-input" required>
                            <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="sc-label" for="i-title">Title</label>
                        <input id="i-title" v-model="form.title" class="sc-input" required maxlength="200" />
                        <p v-if="form.errors.title" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="sc-label" for="i-priority">Priority</label>
                        <select id="i-priority" v-model="form.priority" class="sc-input">
                            <option v-for="p in priorities" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="sc-label" for="i-source">Source URL</label>
                        <input id="i-source" v-model="form.source_url" type="url" class="sc-input" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="sc-label" for="i-notes">Notes</label>
                        <textarea id="i-notes" v-model="form.notes" class="sc-input" rows="3"></textarea>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="sc-btn sc-btn-primary sc-btn-sm" :disabled="form.processing">Capture idea</button>
                    <button type="button" class="sc-btn sc-btn-ghost sc-btn-sm" @click="creating = false">Cancel</button>
                </div>
            </form>

            <!-- BOARD -->
            <div v-if="layout === 'board' && ideas.length" class="flex gap-3 overflow-x-auto pb-2">
                <section
                    v-for="stage in stages"
                    :key="stage"
                    class="flex w-[260px] shrink-0 flex-col gap-2 rounded-xl border p-2"
                    style="border-color: var(--line); background: var(--s1)"
                    @dragover.prevent
                    @drop.prevent="moveTo(dragging, stage)"
                >
                    <h2 class="px-1 text-[12.5px] font-bold text-t3">
                        {{ stage }} <span class="text-t4">({{ byStage[stage]?.length ?? 0 }})</span>
                    </h2>

                    <article
                        v-for="idea in byStage[stage]"
                        :key="idea.id"
                        class="sc-card p-2.5"
                        draggable="true"
                        @dragstart="dragging = idea"
                        @dragend="dragging = null"
                    >
                        <p class="mb-1 text-[13.5px] font-bold leading-snug text-t1">{{ idea.title }}</p>
                        <p class="mb-2 flex items-center gap-2 text-[12px]">
                            <span class="font-semibold" :style="{ color: priorityColor(idea.priority) }">{{ idea.priority }}</span>
                            <span class="text-t3">{{ idea.workspace?.name }}</span>
                        </p>
                        <p v-if="idea.posts_count" class="mb-2 text-[12px] text-t3">{{ idea.posts_count }} draft(s) created</p>

                        <div class="flex gap-1">
                            <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="convert(idea)">
                                <SIcon name="pencil" :size="12" /> To draft
                            </button>
                            <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost !px-2" @click="remove(idea)">
                                <SIcon name="trash" :size="13" :label="`Delete ${idea.title}`" />
                            </button>
                        </div>
                    </article>
                </section>
            </div>

            <!-- LIST -->
            <div v-else-if="layout === 'list' && ideas.length" class="sc-card overflow-hidden">
                <div v-for="idea in ideas" :key="idea.id" class="sc-row flex flex-wrap items-center gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-bold text-t1">{{ idea.title }}</p>
                        <p class="text-[12.5px] text-t3">{{ idea.workspace?.name }} · {{ idea.stage }}</p>
                    </div>
                    <span class="text-[12.5px] font-semibold" :style="{ color: priorityColor(idea.priority) }">{{ idea.priority }}</span>
                    <label class="sr-only" :for="`stage-${idea.id}`">Stage for {{ idea.title }}</label>
                    <select :id="`stage-${idea.id}`" class="sc-input max-w-[150px]" :value="idea.stage"
                            @change="moveTo(idea, $event.target.value)">
                        <option v-for="stage in stages" :key="stage" :value="stage">{{ stage }}</option>
                    </select>
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="convert(idea)">To draft</button>
                </div>
            </div>

            <EmptyState
                v-else
                icon="lightbulb"
                title="No ideas captured yet"
                description="Park half-formed thoughts here, work them up, then convert them straight into drafts."
            />
        </div>
    </AppLayout>
</template>
