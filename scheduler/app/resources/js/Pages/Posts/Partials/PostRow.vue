<script setup>
import { computed } from 'vue';
import SButton from '@/Components/UI/SButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    post: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    changesOpen: Boolean,
    commentsOpen: Boolean,
    changesComment: { type: String, default: '' },
    commentDraft: Object,
    // Resolves a mentioned user id to a display name for this post's workspace.
    resolveMember: { type: Function, required: true },
});

const emit = defineEmits([
    'submit',
    'approve',
    'reject',
    'toggle-changes',
    'update:changesComment',
    'send-changes',
    'toggle-comments',
    'send-comment',
]);

const scheduled = computed(() => (props.post.scheduled_at
    ? new Date(props.post.scheduled_at).toLocaleString([], {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
    : 'Unscheduled'));

const commentCount = computed(() => props.post.comments?.length ?? 0);
const targetCount = computed(() => props.post.targets?.length ?? 0);

const when = (value) => new Date(value).toLocaleString([], {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});
</script>

<template>
    <div>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="whitespace-pre-line text-[14px] leading-relaxed text-t1">
                    {{ post.content || '(no content)' }}
                </p>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11.5px] text-t4">
                    <span class="font-semibold text-t3">{{ post.workspace?.name }}</span>
                    <span>·</span>
                    <span>{{ scheduled }}</span>
                    <span>·</span>
                    <span>{{ targetCount }} target{{ targetCount === 1 ? '' : 's' }}</span>
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <SButton v-if="post.status === 'draft'" size="sm" variant="primary" @click="emit('submit')">
                    Submit for approval
                </SButton>

                <template v-if="post.status === 'pending_approval'">
                    <SButton size="sm" variant="success" @click="emit('approve')">✓ Approve</SButton>
                    <SButton size="sm" variant="warn" @click="emit('toggle-changes')">Request changes</SButton>
                    <SButton size="sm" variant="danger" @click="emit('reject')">Reject</SButton>
                </template>

                <StatusBadge :status="post.status" />
            </div>
        </div>

        <!-- Request-changes note -->
        <div v-if="changesOpen" class="mt-4 flex flex-wrap gap-2.5">
            <textarea
                :value="changesComment"
                rows="2"
                placeholder="What needs to change? (optional)"
                class="sc-input min-w-[240px] flex-1"
                @input="emit('update:changesComment', $event.target.value)"
            ></textarea>
            <SButton variant="warn" class="self-start" @click="emit('send-changes')">Send</SButton>
        </div>

        <!-- Comment thread -->
        <div class="mt-3">
            <button
                type="button"
                class="text-[12px] font-semibold text-t4 transition-colors hover:text-t2"
                @click="emit('toggle-comments')"
            >
                💬 {{ commentCount }} comment{{ commentCount === 1 ? '' : 's' }}
            </button>

            <div v-if="commentsOpen" class="mt-3 space-y-3 border-t pt-3.5" style="border-color: var(--line)">
                <div v-for="comment in post.comments" :key="comment.id" class="flex gap-3">
                    <span
                        class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold"
                        style="background: linear-gradient(135deg, var(--sc-accent-2), var(--sc-accent)); color: #fff"
                    >{{ (comment.user?.name ?? '?').charAt(0).toUpperCase() }}</span>

                    <div class="min-w-0">
                        <p class="text-[12.5px]">
                            <span class="font-bold text-t1">{{ comment.user?.name ?? 'Someone' }}</span>
                            <span class="ml-2 text-[11px] text-t4">{{ when(comment.created_at) }}</span>
                        </p>
                        <p class="mt-0.5 text-[13px] leading-relaxed text-t2">
                            {{ comment.body }}
                            <span
                                v-for="uid in (comment.mentions ?? [])"
                                :key="uid"
                                class="ml-1 text-[12px] font-bold"
                                style="color: var(--sc-accent-text)"
                            >@{{ resolveMember(uid) }}</span>
                        </p>
                    </div>
                </div>

                <div v-if="commentDraft" class="space-y-2.5">
                    <textarea
                        v-model="commentDraft.body"
                        rows="2"
                        placeholder="Reply — @mention a teammate…"
                        class="sc-input"
                    ></textarea>

                    <div v-if="members.length" class="flex flex-wrap items-center gap-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-t4">Mention</span>
                        <label
                            v-for="m in members"
                            :key="m.id"
                            class="inline-flex cursor-pointer items-center gap-1.5 text-[12px] text-t2"
                        >
                            <input type="checkbox" :value="m.id" v-model="commentDraft.mentions" class="sc-check !size-4" />
                            {{ m.name }}
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <SButton
                            size="sm"
                            variant="primary"
                            :disabled="!commentDraft.body.trim()"
                            @click="emit('send-comment')"
                        >Comment</SButton>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
