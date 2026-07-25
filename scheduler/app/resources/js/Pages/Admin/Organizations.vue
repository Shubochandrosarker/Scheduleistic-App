<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    organizations: Array,
    stats: Object,
});

const query = ref('');

const suspend = (id) => router.post(route('admin.organizations.suspend', id), {}, { preserveScroll: true });
const impersonate = (id) => router.post(route('admin.organizations.impersonate', id));

const PLAN_TONES = { free: 'neutral', pro: 'accent', agency: 'ok', scale: 'alt' };

const rows = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.organizations.filter((org) => !q
        || org.name?.toLowerCase().includes(q)
        || org.plan?.toLowerCase().includes(q)
        || org.owner?.email?.toLowerCase().includes(q));
});

const initials = (name) => String(name ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
</script>

<template>
    <AppLayout title="Control plane">
        <PageHeader eyebrow="Platform admin">
            <template #title>Control plane</template>
            <template #subtitle>
                Every tenant on this instance. Suspend, reactivate, or impersonate — all of it
                audit-logged.
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Organizations" :value="stats.organizations" tone="neutral" delta="On this instance" />
            <StatCard label="Active subscriptions" :value="stats.subscribed" tone="ok" delta="Paying tenants" />
            <StatCard
                label="Suspended"
                :value="organizations.filter((o) => o.suspended).length"
                tone="bad"
                delta="Publishing tooling disabled"
            />
            <StatCard
                label="Client workspaces"
                :value="organizations.reduce((sum, o) => sum + (o.workspaces ?? 0), 0)"
                tone="alt"
                delta="Across every tenant"
            />
        </div>

        <SCard class="mt-4" flush title="Organizations">
            <template #actions>
                <input v-model="query" type="search" class="sc-input !w-56 !py-2 !text-[12.5px]" placeholder="Filter by name, plan, owner…" />
            </template>

            <EmptyState
                v-if="rows.length === 0"
                icon="⌕"
                title="No organizations match"
                description="Clear the filter to see every tenant on the platform."
            />

            <div v-else class="sc-scroll overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="border-b" style="border-color: var(--line)">
                            <th class="px-5 py-3 sc-eyebrow font-bold">Organization</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Plan</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Workspaces</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="org in rows" :key="org.id" class="sc-row">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg text-[11px] font-extrabold"
                                        style="background: linear-gradient(135deg, var(--sc-accent), var(--sc-accent-2)); color: var(--on-acc)"
                                    >{{ initials(org.name) }}</span>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 text-[13.5px] font-bold text-t1">
                                            <span class="truncate">{{ org.name }}</span>
                                            <SBadge v-if="org.suspended" tone="bad">Suspended</SBadge>
                                        </p>
                                        <p class="truncate text-[11.5px] text-t4">{{ org.owner?.email ?? 'No owner' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <SBadge :tone="PLAN_TONES[org.plan] ?? 'neutral'">
                                    <span class="capitalize">{{ org.plan }}</span>
                                </SBadge>
                                <span v-if="org.subscribed" class="ml-2 text-[11px] font-semibold" style="color: var(--ok)">
                                    subscribed
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-[13px] text-t2">{{ org.workspaces }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="impersonate(org.id)">
                                        Impersonate
                                    </button>
                                    <button
                                        type="button"
                                        class="sc-btn sc-btn-sm"
                                        :class="org.suspended ? 'sc-btn-success' : 'sc-btn-danger'"
                                        @click="suspend(org.id)"
                                    >{{ org.suspended ? 'Reactivate' : 'Suspend' }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </SCard>
    </AppLayout>
</template>
