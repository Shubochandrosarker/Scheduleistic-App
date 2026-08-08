<script setup>
import { reactive, ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    organizations: Object,
    filters: Object,
    planOptions: Array,
    stats: Object,
});

const search = ref(props.filters.search ?? '');
const filters = reactive({
    status: props.filters.status ?? '',
    plan: props.filters.plan ?? '',
    sort: props.filters.sort ?? 'created_at',
    direction: props.filters.direction ?? 'desc',
});

const apply = () => {
    router.get(route('admin.organizations.index'), {
        ...filters,
        search: search.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(apply, 350);
});

const sortBy = (column) => {
    filters.direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
    filters.sort = column;
    apply();
};

const suspend = (id) => router.post(route('admin.organizations.suspend', id), {}, { preserveScroll: true });

const impersonate = (id) => {
    if (!window.confirm('Impersonate this organization\'s owner? You will need to re-confirm your password.')) return;
    router.post(route('admin.organizations.impersonate', id));
};

const PLAN_TONES = { free: 'neutral', solo: 'cyan', pro: 'accent', agency: 'ok', scale: 'alt', enterprise: 'alt' };

const initials = (name) => String(name ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
</script>

<template>
    <AppLayout title="Control plane">
        <PageHeader eyebrow="Platform admin">
            <template #title>Organizations</template>
            <template #subtitle>
                Every tenant on this instance. Suspend, reactivate, or impersonate — all of it audit-logged.
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Organizations" :value="stats.organizations" tone="neutral" delta="On this instance" />
            <StatCard label="Active subscriptions" :value="stats.subscribed" tone="ok" delta="Paying tenants" />
            <StatCard label="Suspended" :value="stats.suspended" tone="bad" delta="Publishing tooling disabled" />
            <StatCard label="This page" :value="organizations.data.length" tone="alt" :delta="`of ${organizations.total} total`" />
        </div>

        <SCard class="mt-4" flush title="Organizations">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="search"
                        class="sc-input !w-56 !py-2 !text-[12.5px]"
                        placeholder="Name, owner, domain, Stripe ID…"
                        aria-label="Search organizations"
                    />
                    <select v-model="filters.status" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="Filter by status" @change="apply">
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <select v-model="filters.plan" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="Filter by effective plan" @change="apply">
                        <option value="">Any plan</option>
                        <option v-for="plan in planOptions" :key="plan.key" :value="plan.key">{{ plan.name }}</option>
                    </select>
                </div>
            </template>

            <EmptyState
                v-if="organizations.data.length === 0"
                icon="search"
                title="No organizations match"
                description="Try a different search term or clear the filters."
            />

            <div v-else class="sc-scroll overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-left">
                    <thead>
                        <tr class="border-b" style="border-color: var(--line)">
                            <th class="px-5 py-3">
                                <button type="button" class="sc-eyebrow font-bold" @click="sortBy('name')">Organization</button>
                            </th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Plan</th>
                            <th class="px-5 py-3">
                                <button type="button" class="sc-eyebrow font-bold" @click="sortBy('workspaces_count')">Workspaces</button>
                            </th>
                            <th class="px-5 py-3 sc-eyebrow font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="org in organizations.data" :key="org.id" class="sc-row">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg text-[11px] font-extrabold"
                                        style="background: linear-gradient(135deg, var(--sc-accent-2), var(--sc-accent)); color: var(--on-acc)"
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
                                <SBadge :tone="PLAN_TONES[org.effective_plan] ?? 'neutral'">
                                    <span class="capitalize">{{ org.effective_plan }}</span>
                                </SBadge>
                                <span
                                    v-if="org.has_override"
                                    class="ml-1.5 text-[11px] font-semibold"
                                    style="color: var(--sc-accent-text)"
                                    :title="`Billed plan: ${org.base_plan}`"
                                >granted</span>
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

            <nav v-if="organizations.links?.length > 3" class="flex flex-wrap justify-center gap-1 p-4" aria-label="Pagination">
                <template v-for="link in organizations.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-state
                        preserve-scroll
                        class="sc-pill"
                        :class="link.active ? 'sc-pill-on' : ''"
                        :aria-current="link.active ? 'page' : undefined"
                        v-html="link.label"
                    />
                    <span v-else class="sc-pill opacity-40" v-html="link.label" />
                </template>
            </nav>
        </SCard>
    </AppLayout>
</template>
