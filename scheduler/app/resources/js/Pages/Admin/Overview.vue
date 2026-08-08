<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    stats: Object,
    warnings: Array,
    recentSignups: Array,
    recentAuditEvents: Array,
});

const WARNING_TONE = { past_due: 'warn', suspended_organizations: 'bad', suspended_users: 'bad' };

const actionLabel = (action) => action.replace(/[._]/g, ' ');

const formatDate = (value) => new Date(value).toLocaleString();
</script>

<template>
    <AppLayout title="Control plane">
        <PageHeader eyebrow="Platform admin">
            <template #title>Overview</template>
            <template #subtitle>Platform-wide totals and recent activity across every organization.</template>
            <template #actions>
                <Link :href="route('admin.users.index')" class="sc-btn sc-btn-sm sc-btn-secondary">Users</Link>
                <Link :href="route('admin.organizations.index')" class="sc-btn sc-btn-sm sc-btn-secondary">Organizations</Link>
            </template>
        </PageHeader>

        <div v-if="warnings.length" class="mt-4 flex flex-col gap-2">
            <div
                v-for="warning in warnings"
                :key="warning.type"
                class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-[13px] font-semibold"
                style="border-color: var(--warn-border, var(--line)); background: rgba(245,158,11,0.08); color: var(--warn)"
            >
                {{ warning.message }}
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Users" :value="stats.users.total" tone="neutral" :delta="`${stats.users.platform_admins} platform admin${stats.users.platform_admins === 1 ? '' : 's'}`" />
            <StatCard label="Suspended users" :value="stats.users.suspended" tone="bad" delta="Individually suspended accounts" />
            <StatCard label="Organizations" :value="stats.organizations.total" tone="alt" :delta="`${stats.organizations.with_active_override} with a plan override`" />
            <StatCard label="Suspended orgs" :value="stats.organizations.suspended" tone="bad" delta="Publishing tooling disabled" />
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <SCard title="Subscriptions">
                <template #actions>
                    <span class="text-[11.5px] text-t4">Local Cashier state — not a live Stripe query</span>
                </template>
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div v-for="status in ['active', 'trialing', 'past_due', 'canceled']" :key="status">
                        <dt class="sc-eyebrow text-t4">{{ status.replace('_', ' ') }}</dt>
                        <dd class="mt-1 text-[22px] font-extrabold text-t1">{{ stats.subscriptions[status] ?? 0 }}</dd>
                    </div>
                </dl>
            </SCard>

            <SCard title="Organizations per effective plan">
                <ul class="flex flex-col gap-2">
                    <li v-for="(count, plan) in stats.organizations.per_plan" :key="plan" class="flex items-center justify-between text-[13px]">
                        <span class="font-semibold capitalize text-t2">{{ plan }}</span>
                        <span class="font-bold text-t1">{{ count }}</span>
                    </li>
                </ul>
            </SCard>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <SCard flush title="Recent signups">
                <EmptyState v-if="!recentSignups.length" icon="users" title="No signups yet" description="New accounts will appear here." />
                <ul v-else class="divide-y" style="border-color: var(--line)">
                    <li v-for="user in recentSignups" :key="user.id" class="flex items-center justify-between px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-bold text-t1">{{ user.name }}</p>
                            <p class="truncate text-[11.5px] text-t4">{{ user.email }}</p>
                        </div>
                        <span class="shrink-0 text-[11.5px] text-t3">{{ formatDate(user.created_at) }}</span>
                    </li>
                </ul>
            </SCard>

            <SCard flush title="Recent audit events">
                <template #actions>
                    <Link :href="route('admin.overview')" class="text-[11.5px] font-semibold text-t4">Refresh</Link>
                </template>
                <EmptyState
                    v-if="!recentAuditEvents.length"
                    icon="shield"
                    title="No audit events yet"
                    description="Privileged admin and organization actions will appear here."
                />
                <ul v-else class="divide-y" style="border-color: var(--line)">
                    <li v-for="event in recentAuditEvents" :key="event.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-bold capitalize text-t1">{{ actionLabel(event.action) }}</p>
                            <p class="truncate text-[11.5px] text-t4">
                                {{ event.actor?.name ?? 'System' }}
                                <span v-if="event.organization"> · {{ event.organization.name }}</span>
                            </p>
                        </div>
                        <span class="shrink-0 text-[11.5px] text-t3">{{ formatDate(event.created_at) }}</span>
                    </li>
                </ul>
            </SCard>
        </div>
    </AppLayout>
</template>
