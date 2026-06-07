<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    workspace: Object,
    channels: Array,
    providers: Array,
});

const disconnect = (id) => {
    if (confirm('Disconnect this channel?')) {
        useForm({}).delete(route('workspaces.channels.destroy', [props.workspace.id, id]), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :title="`Channels — ${workspace.name}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Channels — {{ workspace.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Connect new -->
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Connect a social account</h3>
                    <div class="flex flex-wrap gap-3">
                        <a
                            v-for="p in providers"
                            :key="p.key"
                            :href="route('workspaces.channels.connect', [workspace.id, p.key])"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700"
                        >
                            Connect {{ p.label }}
                        </a>
                    </div>
                </div>

                <!-- Connected -->
                <div class="bg-white shadow sm:rounded-lg divide-y">
                    <div v-if="channels.length === 0" class="p-6 text-gray-500">
                        No channels connected yet.
                    </div>
                    <div v-for="c in channels" :key="c.id" class="p-6 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900">{{ c.name }}</p>
                            <p class="text-sm text-gray-500">{{ c.provider }} · {{ c.status }}</p>
                        </div>
                        <DangerButton @click="disconnect(c.id)">Disconnect</DangerButton>
                    </div>
                </div>

                <Link :href="route('workspaces.index')" class="text-indigo-600 hover:underline text-sm">
                    ← Back to workspaces
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
