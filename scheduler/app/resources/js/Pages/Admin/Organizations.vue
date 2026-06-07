<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    organizations: Array,
    stats: Object,
});

const suspend = (id) => router.post(route('admin.organizations.suspend', id), {}, { preserveScroll: true });
const impersonate = (id) => router.post(route('admin.organizations.impersonate', id));
</script>

<template>
    <AppLayout title="Admin — Organizations">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Platform admin — Organizations</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="bg-white shadow sm:rounded-lg p-6">
                        <p class="text-xs uppercase text-gray-400">Organizations</p>
                        <p class="text-2xl font-semibold">{{ stats.organizations }}</p>
                    </div>
                    <div class="bg-white shadow sm:rounded-lg p-6">
                        <p class="text-xs uppercase text-gray-400">Active subscriptions</p>
                        <p class="text-2xl font-semibold">{{ stats.subscribed }}</p>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg divide-y">
                    <div v-for="org in organizations" :key="org.id" class="p-6 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ org.name }}
                                <span v-if="org.suspended" class="ml-2 text-xs text-red-600">(suspended)</span>
                            </p>
                            <p class="text-sm text-gray-500 truncate">
                                {{ org.owner?.email }} · plan: {{ org.plan }} · {{ org.workspaces }} workspace(s)
                                <span v-if="org.subscribed" class="text-green-600">· subscribed</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="impersonate(org.id)"
                                class="text-xs px-2.5 py-1 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                                Impersonate
                            </button>
                            <button @click="suspend(org.id)"
                                class="text-xs px-2.5 py-1 rounded-md"
                                :class="org.suspended ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
                                {{ org.suspended ? 'Unsuspend' : 'Suspend' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
