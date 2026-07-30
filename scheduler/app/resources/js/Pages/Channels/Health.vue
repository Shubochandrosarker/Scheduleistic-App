<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { network } from '@/networks';
import { useTerms } from '@/composables/useCapabilities';

defineProps({
    channels: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const { many } = useTerms();

/**
 * Every state carries a word as well as a colour — status must never be
 * communicated by colour alone.
 */
const STATE = {
    connected: { label: 'Connected', color: 'var(--ok)', icon: 'check' },
    token_expiring: { label: 'Expiring soon', color: 'var(--warn)', icon: 'clock' },
    token_expired: { label: 'Expired — reconnect', color: 'var(--bad)', icon: 'alert' },
    permission_missing: { label: 'Missing permission', color: 'var(--bad)', icon: 'lock' },
    provider_unavailable: { label: 'Network unavailable', color: 'var(--warn)', icon: 'alert' },
    rate_limited: { label: 'Rate limited', color: 'var(--warn)', icon: 'clock' },
    publish_failed: { label: 'Last publish failed', color: 'var(--warn)', icon: 'alert' },
};

const stateOf = (key) => STATE[key] ?? { label: key, color: 'var(--t3)', icon: 'info' };

const when = (value) => value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : 'Never';

const refresh = () => router.post(route('channels.health.refresh'), {}, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Social profile health">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="sc-title">{{ many('channel') }}</h1>
                    <p class="sc-sub">Connection health, token expiry and last activity for every connected profile.</p>
                </div>
                <button type="button" class="sc-btn sc-btn-secondary" @click="refresh">
                    <SIcon name="refresh" :size="15" /> Re-check now
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sc-card p-4">
                    <p class="text-[12px] font-bold text-t4">Healthy</p>
                    <p class="text-[24px] font-extrabold" style="color: var(--ok)">{{ summary.healthy ?? 0 }}</p>
                </div>
                <div class="sc-card p-4">
                    <p class="text-[12px] font-bold text-t4">Needs attention</p>
                    <p class="text-[24px] font-extrabold" style="color: var(--warn)">{{ summary.warning ?? 0 }}</p>
                </div>
                <div class="sc-card p-4">
                    <p class="text-[12px] font-bold text-t4">Cannot publish</p>
                    <p class="text-[24px] font-extrabold" style="color: var(--bad)">{{ summary.blocked ?? 0 }}</p>
                </div>
            </div>

            <div v-if="channels.length" class="sc-card overflow-hidden">
                <article v-for="channel in channels" :key="channel.id" class="sc-row flex flex-wrap items-start gap-3 p-3.5">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg text-[12px] font-extrabold"
                        :style="{ background: network(channel.provider).color, color: '#fff' }"
                    >{{ network(channel.provider).short }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-bold text-t1">{{ channel.name }}</p>
                        <p class="text-[12.5px] text-t3">
                            {{ network(channel.provider).label }} · {{ channel.workspace?.name }}
                        </p>
                        <p v-for="issue in channel.issues" :key="issue.state" class="mt-1 text-[12.5px]" :style="{ color: stateOf(issue.state).color }">
                            {{ issue.message }}
                        </p>
                    </div>

                    <div class="min-w-[190px] text-[12.5px] text-t3">
                        <p>Last publish: {{ when(channel.last_published_at) }}</p>
                        <p>Last metrics sync: {{ when(channel.last_metrics_sync_at) }}</p>
                        <p>Token expires: {{ when(channel.token_expires_at) }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span class="flex items-center gap-1.5 text-[12.5px] font-bold" :style="{ color: stateOf(channel.health_state).color }">
                            <SIcon :name="stateOf(channel.health_state).icon" :size="14" />
                            {{ stateOf(channel.health_state).label }}
                        </span>
                        <a
                            v-if="!channel.can_publish"
                            :href="route('workspaces.channels.index', channel.workspace.id)"
                            class="sc-btn sc-btn-sm sc-btn-primary"
                        >Reconnect</a>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                icon="plug"
                title="No profiles connected"
                :description="`Connect a ${many('channel').toLowerCase()} to start publishing.`"
            />
        </div>
    </AppLayout>
</template>
