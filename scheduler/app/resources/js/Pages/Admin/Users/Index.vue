<script setup>
import { reactive, ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import SButton from '@/Components/UI/SButton.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = ref(props.filters.search ?? '');
const filters = reactive({
    status: props.filters.status ?? '',
    verified: props.filters.verified ?? '',
    platform_admin: props.filters.platform_admin ?? '',
    sort: props.filters.sort ?? 'created_at',
    direction: props.filters.direction ?? 'desc',
});

const apply = () => {
    router.get(route('admin.users.index'), {
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

const suspend = (user) => {
    if (user.suspended) {
        router.post(route('admin.users.suspend', user.id), {}, { preserveScroll: true });
        return;
    }

    const reason = window.prompt(`Reason for suspending ${user.name}? (required, visible in the audit log)`);
    if (!reason) return;

    router.post(route('admin.users.suspend', user.id), { reason }, { preserveScroll: true });
};

const sendResetLink = (user) => {
    if (!window.confirm(`Send a password-reset email to ${user.email}?`)) return;
    router.post(route('admin.users.reset-link', user.id), {}, { preserveScroll: true });
};

const revokeSessions = (user) => {
    if (!window.confirm(`Sign ${user.name} out of every device? They will need to log in again.`)) return;
    router.post(route('admin.users.revoke-sessions', user.id), {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Users">
        <PageHeader eyebrow="Platform admin">
            <template #title>Users</template>
            <template #subtitle>Every account on this instance. Suspension and session actions are audit-logged.</template>
        </PageHeader>

        <SCard class="mt-4" flush title="Users">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="search"
                        class="sc-input !w-56 !py-2 !text-[12.5px]"
                        placeholder="Search name or email…"
                        aria-label="Search users"
                    />
                    <select v-model="filters.status" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="Filter by status" @change="apply">
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <select v-model="filters.verified" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="Filter by verification" @change="apply">
                        <option value="">Any verification</option>
                        <option value="yes">Verified</option>
                        <option value="no">Unverified</option>
                    </select>
                    <select v-model="filters.platform_admin" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="Filter by platform admin" @change="apply">
                        <option value="">Any role</option>
                        <option value="yes">Platform admins</option>
                        <option value="no">Non-admins</option>
                    </select>
                </div>
            </template>

            <EmptyState
                v-if="users.data.length === 0"
                icon="search"
                title="No users match"
                description="Try a different search term or clear the filters."
            />

            <div v-else class="sc-scroll overflow-x-auto">
                <table class="w-full min-w-[880px] border-collapse text-left">
                    <thead>
                        <tr class="border-b" style="border-color: var(--line)">
                            <th class="px-5 py-3">
                                <button type="button" class="sc-eyebrow font-bold" @click="sortBy('name')">Name</button>
                            </th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Organization</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Memberships</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Status</th>
                            <th class="px-5 py-3">
                                <button type="button" class="sc-eyebrow font-bold" @click="sortBy('created_at')">Joined</button>
                            </th>
                            <th class="px-5 py-3 sc-eyebrow font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id" class="sc-row">
                            <td class="px-5 py-3.5">
                                <p class="flex items-center gap-2 text-[13.5px] font-bold text-t1">
                                    <span class="truncate">{{ user.name }}</span>
                                    <SBadge v-if="user.is_platform_admin" tone="alt">Admin</SBadge>
                                    <SBadge v-if="!user.email_verified" tone="neutral">Unverified</SBadge>
                                </p>
                                <p class="truncate text-[11.5px] text-t4">{{ user.email }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-[13px] text-t2">
                                {{ user.current_team?.name ?? '—' }}
                                <span v-if="user.owned_teams_count" class="text-[11px] text-t4">(owns {{ user.owned_teams_count }})</span>
                            </td>
                            <td class="px-5 py-3.5 text-[13px] text-t2">
                                {{ user.memberships_count }} org{{ user.memberships_count === 1 ? '' : 's' }},
                                {{ user.workspaces_count }} workspace{{ user.workspaces_count === 1 ? '' : 's' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <SBadge v-if="user.suspended" tone="bad">Suspended</SBadge>
                                <SBadge v-else tone="ok">Active</SBadge>
                                <p v-if="user.suspension_reason" class="mt-1 max-w-[200px] truncate text-[11px] text-t4" :title="user.suspension_reason">
                                    {{ user.suspension_reason }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5 text-[12.5px] text-t3">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="sendResetLink(user)">
                                        Reset link
                                    </button>
                                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="revokeSessions(user)">
                                        Revoke sessions
                                    </button>
                                    <button
                                        type="button"
                                        class="sc-btn sc-btn-sm"
                                        :class="user.suspended ? 'sc-btn-success' : 'sc-btn-danger'"
                                        @click="suspend(user)"
                                    >{{ user.suspended ? 'Reactivate' : 'Suspend' }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="users.links?.length > 3" class="flex flex-wrap justify-center gap-1 p-4" aria-label="Pagination">
                <template v-for="link in users.links" :key="link.label">
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
