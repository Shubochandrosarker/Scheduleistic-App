<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    currentPlan: String,
    plans: Array,
    usage: Object,
    subscribed: Boolean,
});

const subscribe = (plan) => router.post(route('billing.checkout', plan));
const portal = () => (window.location = route('billing.portal'));

const label = (v) => (v === -1 ? 'Unlimited' : v);
</script>

<template>
    <AppLayout title="Billing">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Billing & Plan</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Usage -->
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Current plan: <span class="capitalize">{{ currentPlan }}</span>
                        </h3>
                        <SecondaryButton v-if="subscribed" @click="portal">Manage subscription</SecondaryButton>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div v-for="(v, key) in usage" :key="key" class="border rounded-lg p-4">
                            <p class="text-xs uppercase text-gray-400">{{ key.replace('_', ' ') }}</p>
                            <p class="text-lg font-semibold text-gray-900">
                                {{ v.usage }} <span class="text-gray-400 text-sm">/ {{ label(v.limit) }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Plans -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <div v-for="p in plans" :key="p.key" class="bg-white shadow sm:rounded-lg p-6 flex flex-col">
                        <h3 class="text-lg font-semibold text-gray-900 capitalize">{{ p.name }}</h3>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            <span v-if="p.price">${{ p.price }}<span class="text-sm font-normal text-gray-400">/mo</span></span>
                            <span v-else class="text-gray-500">Free</span>
                        </p>
                        <ul class="mt-4 space-y-1 text-sm text-gray-600 flex-1">
                            <li>{{ label(p.limits.workspaces) }} workspaces</li>
                            <li>{{ label(p.limits.channels) }} channels</li>
                            <li>{{ label(p.limits.members) }} team members</li>
                            <li>{{ label(p.limits.monthly_posts) }} posts / month</li>
                            <li v-if="p.features && p.features.client_approval">Client approval</li>
                            <li v-if="p.features && p.features.white_label">White-label branding</li>
                        </ul>
                        <PrimaryButton v-if="p.key !== currentPlan && p.key !== 'free'"
                            class="mt-4 justify-center" @click="subscribe(p.key)">
                            Upgrade to {{ p.name }}
                        </PrimaryButton>
                        <span v-else-if="p.key === currentPlan" class="mt-4 text-center text-sm text-green-600 font-medium">
                            Your current plan
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
