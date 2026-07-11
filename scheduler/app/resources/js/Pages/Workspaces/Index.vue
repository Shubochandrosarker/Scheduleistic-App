<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

defineProps({
    workspaces: Array,
});

const form = useForm({
    name: '',
    client_name: '',
    timezone: 'UTC',
});

const create = () => form.post(route('workspaces.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const destroy = (id) => {
    if (confirm('Delete this workspace and all its channels?')) {
        useForm({}).delete(route('workspaces.destroy', id), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout title="Workspaces">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Workspaces (Clients)</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- New workspace -->
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add a client workspace</h3>
                    <form @submit.prevent="create" class="grid gap-4 sm:grid-cols-4 items-end">
                        <div>
                            <InputLabel for="name" value="Workspace name" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <InputLabel for="client_name" value="Client name" />
                            <TextInput id="client_name" v-model="form.client_name" type="text" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel for="timezone" value="Timezone" />
                            <TextInput id="timezone" v-model="form.timezone" type="text" class="mt-1 block w-full" />
                        </div>
                        <PrimaryButton :disabled="form.processing">Create</PrimaryButton>
                    </form>
                </div>

                <!-- List -->
                <div class="bg-white shadow sm:rounded-lg divide-y">
                    <div v-if="workspaces.length === 0" class="p-6 text-gray-500">
                        No workspaces yet. Add your first client above.
                    </div>
                    <div v-for="ws in workspaces" :key="ws.id" class="p-6 flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p class="font-medium text-gray-900">{{ ws.name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ ws.client_name || '—' }} · {{ ws.timezone }} · {{ ws.channels_count }} channel(s)
                                · {{ ws.feeds_count }} feed(s) · {{ ws.time_slots_count }} time slot(s)
                            </p>
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <Link :href="route('workspaces.channels.index', ws.id)" class="text-indigo-600 hover:underline text-sm">
                                Manage channels
                            </Link>
                            <Link :href="route('workspaces.time-slots.index', ws.id)" class="text-indigo-600 hover:underline text-sm">
                                Time slots
                            </Link>
                            <Link :href="route('workspaces.feeds.index', ws.id)" class="text-indigo-600 hover:underline text-sm">
                                Feeds
                            </Link>
                            <Link :href="route('workspaces.import.create', ws.id)" class="text-indigo-600 hover:underline text-sm">
                                Import CSV
                            </Link>
                            <DangerButton @click="destroy(ws.id)">Delete</DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
