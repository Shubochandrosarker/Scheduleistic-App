<script setup>
import { reactive, ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    actionOptions: Array,
});

const actor = ref(props.filters.actor ?? '');
const filters = reactive({
    action: props.filters.action ?? '',
    created_from: props.filters.created_from ?? '',
    created_to: props.filters.created_to ?? '',
});

const apply = () => {
    router.get(route('admin.audit-logs.index'), {
        ...filters,
        actor: actor.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

let searchTimer = null;
watch(actor, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(apply, 350);
});

const expanded = ref(null);
const toggle = (id) => { expanded.value = expanded.value === id ? null : id; };

const actionLabel = (action) => action.replace(/[._]/g, ' ');
const fmt = (value) => new Date(value).toLocaleString();
</script>

<template>
    <AppLayout title="Audit log">
        <PageHeader eyebrow="Platform admin">
            <template #title>Audit log</template>
            <template #subtitle>Every privileged action recorded by the platform. Read-only — nothing here can be edited or deleted.</template>
        </PageHeader>

        <SCard class="mt-4" flush title="Events">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="actor"
                        type="search"
                        class="sc-input !w-52 !py-2 !text-[12.5px]"
                        placeholder="Actor name or email…"
                        aria-label="Filter by actor"
                    />
                    <select v-model="filters.action" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="Filter by action" @change="apply">
                        <option value="">Any action</option>
                        <option v-for="action in actionOptions" :key="action" :value="action">{{ actionLabel(action) }}</option>
                    </select>
                    <input v-model="filters.created_from" type="date" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="From date" @change="apply" />
                    <input v-model="filters.created_to" type="date" class="sc-input !w-auto !py-2 !text-[12.5px]" aria-label="To date" @change="apply" />
                </div>
            </template>

            <EmptyState v-if="logs.data.length === 0" icon="shield" title="No matching events" description="Try clearing the filters." />

            <div v-else class="sc-scroll overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="border-b" style="border-color: var(--line)">
                            <th class="px-5 py-3 sc-eyebrow font-bold">Action</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Actor</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">Organization</th>
                            <th class="px-5 py-3 sc-eyebrow font-bold">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="log in logs.data" :key="log.id">
                            <tr class="sc-row cursor-pointer" @click="toggle(log.id)">
                                <td class="px-5 py-3.5">
                                    <span class="text-[13px] font-bold capitalize text-t1">{{ actionLabel(log.action) }}</span>
                                    <span v-if="log.subject_type" class="ml-1.5 text-[11px] text-t4">{{ log.subject_type }} #{{ log.subject_id }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-[13px] text-t2">
                                    {{ log.actor?.name ?? 'System' }}
                                    <span v-if="log.actor" class="block text-[11px] text-t4">{{ log.actor.email }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-[13px] text-t2">
                                    <Link v-if="log.organization" :href="route('admin.organizations.show', log.organization.id)" class="hover:underline" @click.stop>
                                        {{ log.organization.name }}
                                    </Link>
                                    <span v-else class="text-t4">—</span>
                                </td>
                                <td class="px-5 py-3.5 text-[12.5px] text-t3">{{ fmt(log.created_at) }}</td>
                            </tr>
                            <tr v-if="expanded === log.id" style="background: var(--s2)">
                                <td colspan="4" class="px-5 py-3.5">
                                    <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-t4">Context (redacted automatically at write time)</p>
                                    <pre class="sc-scroll max-h-64 overflow-auto rounded-lg border p-3 text-[11.5px] text-t2" style="border-color: var(--line2); background: var(--s1)">{{ JSON.stringify(log.context, null, 2) || 'No additional context.' }}</pre>
                                    <p v-if="log.ip" class="mt-2 text-[11px] text-t4">IP: {{ log.ip }}</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <nav v-if="logs.links?.length > 3" class="flex flex-wrap justify-center gap-1 p-4" aria-label="Pagination">
                <template v-for="link in logs.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll class="sc-pill" :class="link.active ? 'sc-pill-on' : ''" v-html="link.label" />
                    <span v-else class="sc-pill opacity-40" v-html="link.label" />
                </template>
            </nav>
        </SCard>
    </AppLayout>
</template>
