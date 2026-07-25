<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    workspaces: Array,
});

const showForm = ref(false);

const form = useForm({
    name: '',
    client_name: '',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
});

const create = () => form.post(route('workspaces.store'), {
    preserveScroll: true,
    onSuccess: () => {
        form.reset();
        showForm.value = false;
    },
});

const destroy = (id) => {
    if (confirm('Delete this workspace and all its channels?')) {
        useForm({}).delete(route('workspaces.destroy', id), { preserveScroll: true });
    }
};

const initials = (name) => String(name ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
</script>

<template>
    <AppLayout title="Clients">
        <PageHeader eyebrow="Multi-tenancy">
            <template #title>Client workspaces</template>
            <template #subtitle>
                One workspace per client. Content, channels, and approvals stay sealed off from
                every other client — enforced at the query layer, not by convention.
            </template>
            <template #actions>
                <SButton variant="primary" @click="showForm = !showForm">
                    {{ showForm ? 'Cancel' : '+ Add a client' }}
                </SButton>
            </template>
        </PageHeader>

        <SCard v-if="showForm" class="mb-4" title="New client workspace">
            <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end" @submit.prevent="create">
                <div>
                    <label class="sc-label" for="name">Workspace name</label>
                    <input id="name" v-model="form.name" type="text" class="sc-input" required />
                    <InputError :message="form.errors.name" class="mt-1.5" />
                </div>
                <div>
                    <label class="sc-label" for="client_name">Client name</label>
                    <input id="client_name" v-model="form.client_name" type="text" class="sc-input" />
                    <InputError :message="form.errors.client_name" class="mt-1.5" />
                </div>
                <div>
                    <label class="sc-label" for="timezone">Timezone</label>
                    <input id="timezone" v-model="form.timezone" type="text" class="sc-input" />
                    <InputError :message="form.errors.timezone" class="mt-1.5" />
                </div>
                <SButton type="submit" variant="primary" :disabled="form.processing">Create workspace</SButton>
            </form>
            <InputError :message="form.errors.plan" class="mt-3" />
        </SCard>

        <EmptyState
            v-if="workspaces.length === 0"
            class="sc-card"
            icon="◆"
            title="No clients yet"
            description="Add your first workspace — it holds that client's channels, queue, and approvals."
        >
            <SButton variant="primary" @click="showForm = true">Add a client →</SButton>
        </EmptyState>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="ws in workspaces" :key="ws.id" class="sc-card flex flex-col p-5">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl text-[13px] font-extrabold"
                        style="background: linear-gradient(135deg, var(--sc-accent), var(--sc-accent-2)); color: var(--on-acc)"
                    >{{ initials(ws.name) }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-bold text-t1">{{ ws.name }}</p>
                        <p class="truncate text-[12px] text-t4">
                            {{ ws.client_name || 'No client name' }} · {{ ws.timezone }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <SBadge :tone="ws.channels_count ? 'accent' : 'neutral'">{{ ws.channels_count }} channels</SBadge>
                    <SBadge tone="neutral">{{ ws.time_slots_count }} slots</SBadge>
                    <SBadge tone="neutral">{{ ws.feeds_count }} feeds</SBadge>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2 border-t pt-4" style="border-color: var(--line)">
                    <SButton size="sm" :href="route('workspaces.channels.index', ws.id)">Channels</SButton>
                    <SButton size="sm" :href="route('workspaces.time-slots.index', ws.id)">Queue slots</SButton>
                    <SButton size="sm" :href="route('workspaces.feeds.index', ws.id)">Feeds</SButton>
                    <SButton size="sm" :href="route('workspaces.import.create', ws.id)">Import CSV</SButton>
                </div>

                <button
                    type="button"
                    class="mt-3 self-start text-[11.5px] font-bold transition-opacity hover:opacity-70"
                    style="color: var(--bad)"
                    @click="destroy(ws.id)"
                >Delete workspace</button>
            </article>
        </div>
    </AppLayout>
</template>
