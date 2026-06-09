<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    stats: Object,
    plan: String,
    usage: Object,
    upcoming: Array,
});

const branding = usePage().props.branding ?? {};
const label = (v) => (v === -1 ? '∞' : v);

const cards = [
    { key: 'workspaces', label: 'Workspaces' },
    { key: 'channels', label: 'Connected channels' },
    { key: 'scheduled', label: 'Scheduled posts' },
    { key: 'published_month', label: 'Published this month' },
];

const actions = [
    { label: 'Compose a post', route: 'posts.create', color: 'bg-indigo-600 hover:bg-indigo-700' },
    { label: 'Workspaces', route: 'workspaces.index', color: 'bg-gray-700 hover:bg-gray-800' },
    { label: 'Analytics', route: 'analytics.index', color: 'bg-gray-700 hover:bg-gray-800' },
    { label: 'Billing', route: 'billing.index', color: 'bg-gray-700 hover:bg-gray-800' },
];
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Welcome + plan -->
                <div class="bg-white shadow sm:rounded-lg p-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Welcome to {{ branding.name || 'SOCIALISTIC' }}
                        </h3>
                        <p class="text-sm text-gray-500">{{ branding.tagline || 'Schedule and publish across every network.' }}</p>
                    </div>
                    <span class="text-xs uppercase tracking-wide px-3 py-1 rounded-full bg-indigo-50 text-indigo-700">
                        {{ plan }} plan
                    </span>
                </div>

                <!-- Stat cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="c in cards" :key="c.key" class="bg-white shadow sm:rounded-lg p-6">
                        <p class="text-xs uppercase text-gray-400">{{ c.label }}</p>
                        <p class="text-3xl font-semibold text-gray-900 mt-1">{{ stats[c.key] }}</p>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="flex flex-wrap gap-3">
                    <Link v-for="a in actions" :key="a.route" :href="route(a.route)"
                        :class="`inline-flex items-center px-4 py-2 text-white text-sm rounded-md ${a.color}`">
                        {{ a.label }}
                    </Link>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Upcoming posts -->
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-6 py-4 border-b">
                            <h3 class="font-medium text-gray-900">Upcoming posts</h3>
                        </div>
                        <div class="divide-y">
                            <div v-if="!upcoming || upcoming.length === 0" class="p-6 text-sm text-gray-500">
                                Nothing scheduled yet.
                                <Link :href="route('posts.create')" class="text-indigo-600 hover:underline">Compose one →</Link>
                            </div>
                            <div v-for="p in upcoming" :key="p.id" class="px-6 py-3">
                                <p class="text-sm text-gray-900">{{ p.content }}</p>
                                <p class="text-xs text-gray-400">{{ p.workspace }} · {{ p.scheduled_at }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Usage vs plan -->
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-6 py-4 border-b flex items-center justify-between">
                            <h3 class="font-medium text-gray-900">Plan usage</h3>
                            <Link :href="route('billing.index')" class="text-xs text-indigo-600 hover:underline">Manage →</Link>
                        </div>
                        <div class="p-6 space-y-3">
                            <div v-for="(v, key) in usage" :key="key">
                                <div class="flex justify-between text-sm">
                                    <span class="capitalize text-gray-600">{{ key.replace('_', ' ') }}</span>
                                    <span class="text-gray-400">{{ v.usage }} / {{ label(v.limit) }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded mt-1">
                                    <div class="h-1.5 bg-indigo-500 rounded"
                                        :style="{ width: v.limit === -1 ? '8%' : Math.min(100, (v.usage / Math.max(v.limit, 1)) * 100) + '%' }">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
