<script setup>
import { computed } from 'vue';
import SIcon from '@/Components/UI/SIcon.vue';
import { network, networkSoft } from '@/networks';

/**
 * One post on the calendar.
 *
 * Shows time, thumbnail, networks, brand colour, status, campaign, approval
 * state and — importantly — publishing errors, so a failure is visible without
 * opening anything.
 */
const props = defineProps({
    post: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    selectable: { type: Boolean, default: false },
    draggable: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'toggle-select', 'dragstart', 'dragend']);

const STATUS = {
    draft: { label: 'Draft', color: 'var(--t3)' },
    pending_approval: { label: 'In review', color: 'var(--warn)' },
    scheduled: { label: 'Scheduled', color: 'var(--sc-accent-text)' },
    publishing: { label: 'Publishing', color: 'var(--alt)' },
    published: { label: 'Published', color: 'var(--ok)' },
    partially_failed: { label: 'Partly failed', color: 'var(--warn)' },
    failed: { label: 'Failed', color: 'var(--bad)' },
};

const status = computed(() => STATUS[props.post.status] ?? { label: props.post.status, color: 'var(--t3)' });

const time = computed(() => {
    if (!props.post.scheduled_at) return 'Unscheduled';

    return new Date(props.post.scheduled_at).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
});

const hasErrors = computed(() => (props.post.errors ?? []).length > 0);
</script>

<template>
    <article
        class="group relative w-full rounded-[10px] border p-2 text-left transition-colors"
        :style="{
            background: 'var(--card-bg2)',
            borderColor: hasErrors ? 'var(--bad)' : 'var(--card-line)',
            borderLeft: `3px solid ${post.workspace?.color || 'var(--sc-accent)'}`,
        }"
        :draggable="draggable"
        @dragstart="emit('dragstart', post)"
        @dragend="emit('dragend')"
    >
        <div class="flex items-start gap-2">
            <input
                v-if="selectable"
                type="checkbox"
                class="sc-check mt-0.5"
                :checked="selected"
                :aria-label="`Select post ${post.title || post.excerpt}`"
                @change="emit('toggle-select', post.id)"
            />

            <button type="button" class="min-w-0 flex-1 text-left" @click="emit('open', post.id)">
                <!-- Wraps rather than overflowing: a month cell can be ~150px
                     wide, which is narrower than "9:00 AM · Scheduled". -->
                <div class="mb-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                    <span class="text-[12px] font-bold text-t2">{{ time }}</span>
                    <!-- Status is a dot AND a word: never colour alone. -->
                    <span class="flex min-w-0 items-center gap-1 text-[12px] font-semibold" :style="{ color: status.color }">
                        <span class="size-1.5 shrink-0 rounded-full" :style="{ background: status.color }"></span>
                        <span class="truncate">{{ status.label }}</span>
                    </span>
                </div>

                <p class="mb-1.5 line-clamp-2 text-[13.5px] font-semibold leading-snug text-t1">
                    {{ post.title || post.excerpt || 'Untitled post' }}
                </p>

                <div class="flex flex-wrap items-center gap-1">
                    <span
                        v-for="provider in post.providers"
                        :key="provider"
                        class="rounded px-1.5 py-0.5 text-[11.5px] font-bold"
                        :style="{ background: networkSoft(provider, 0.18), color: network(provider).color }"
                        :title="network(provider).label"
                    >{{ network(provider).short }}</span>

                    <span
                        v-if="post.campaign"
                        class="truncate rounded px-1.5 py-0.5 text-[11.5px] font-semibold"
                        :style="{ background: 'var(--s2)', color: post.campaign.color || 'var(--t2)' }"
                    >{{ post.campaign.name }}</span>

                    <span v-if="post.comment_count" class="flex items-center gap-0.5 text-[11.5px] text-t3">
                        <SIcon name="reply" :size="11" />{{ post.comment_count }}
                    </span>
                </div>
            </button>

            <span v-if="draggable" class="opacity-0 transition-opacity group-hover:opacity-60" aria-hidden="true">
                <SIcon name="grip" :size="14" />
            </span>
        </div>

        <!-- Failures surface on the card itself, not only in the drawer. -->
        <p
            v-if="hasErrors && !compact"
            class="mt-1.5 flex items-start gap-1 rounded px-1.5 py-1 text-[12px] font-semibold"
            style="background: rgba(248,113,113,0.12); color: var(--bad)"
        >
            <SIcon name="alert" :size="12" class="mt-0.5" />
            <span class="min-w-0">{{ post.errors[0].provider }}: {{ post.errors[0].message }}</span>
        </p>
    </article>
</template>
