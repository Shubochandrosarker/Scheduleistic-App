<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    totals: Object,
});

const hasData = Object.keys(props.totals ?? {}).length > 0;
</script>

<template>
    <AppLayout title="Analytics">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Analytics</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="!hasData" class="bg-white shadow sm:rounded-lg p-6 text-gray-500">
                    No metrics yet. Once posts publish, engagement is captured hourly and shown here.
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-4">
                    <div v-for="(value, metric) in totals" :key="metric" class="bg-white shadow sm:rounded-lg p-6">
                        <p class="text-xs uppercase text-gray-400">{{ metric }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ value.toLocaleString() }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
