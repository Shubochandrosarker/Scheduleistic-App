<script setup>
import { ref, reactive } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    workspace: Object,
    channels: Array,
    providers: Array,
});

// Which token-provider form is open, and its field values.
const openForm = ref(null);
const tokenForm = reactive({});

const toggleForm = (p) => {
    if (openForm.value === p.key) {
        openForm.value = null;
        return;
    }
    openForm.value = p.key;
    p.fields.forEach((f) => (tokenForm[f.key] = ''));
};

const submitToken = (p) => {
    router.post(route('workspaces.channels.token', [props.workspace.id, p.key]), { ...tokenForm }, {
        preserveScroll: true,
        onSuccess: () => (openForm.value = null),
    });
};

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
                        <template v-for="p in providers" :key="p.key">
                            <!-- OAuth providers: redirect to connect (disabled if no credentials configured) -->
                            <a
                                v-if="p.type === 'oauth' && p.configured"
                                :href="route('workspaces.channels.connect', [workspace.id, p.key])"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700"
                            >
                                Connect {{ p.label }}
                            </a>
                            <span
                                v-else-if="p.type === 'oauth'"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-400 text-sm rounded-md cursor-not-allowed"
                                title="OAuth credentials not configured on this instance"
                            >
                                {{ p.label }} (not configured)
                            </span>
                            <!-- Token providers: open a credential form -->
                            <button
                                v-else
                                type="button"
                                @click="toggleForm(p)"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700"
                            >
                                Connect {{ p.label }}
                            </button>
                        </template>
                    </div>

                    <!-- Token credential forms -->
                    <template v-for="p in providers" :key="`form-${p.key}`">
                        <form
                            v-if="openForm === p.key"
                            @submit.prevent="submitToken(p)"
                            class="mt-4 border-t pt-4 space-y-3 max-w-md"
                        >
                            <p class="text-sm font-medium text-gray-700">Connect {{ p.label }}</p>
                            <div v-for="f in p.fields" :key="f.key">
                                <label class="block text-xs text-gray-500 mb-1">{{ f.label }}</label>
                                <input
                                    v-model="tokenForm[f.key]"
                                    :type="f.type === 'password' ? 'password' : 'text'"
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm"
                                    required
                                />
                            </div>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700">
                                Save {{ p.label }}
                            </button>
                        </form>
                    </template>
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
