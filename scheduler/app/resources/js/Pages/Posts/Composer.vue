<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    workspaces: Array,
});

const form = useForm({
    workspace_id: props.workspaces[0]?.id ?? null,
    content: '',
    channel_ids: [],
    scheduled_at: '',
    use_queue: false,
    recurring_rule: '',
});

const selectedWorkspace = computed(
    () => props.workspaces.find((w) => w.id === form.workspace_id),
);

const characters = computed(() => form.content.length);

const submit = () => form.post(route('posts.store'));
</script>

<template>
    <AppLayout title="Compose">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compose a post</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow sm:rounded-lg p-6 space-y-6">
                    <div>
                        <InputLabel for="workspace" value="Workspace (client)" />
                        <select id="workspace" v-model="form.workspace_id"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel value="Channels" />
                        <div v-if="selectedWorkspace?.channels?.length" class="mt-2 flex flex-wrap gap-3">
                            <label v-for="c in selectedWorkspace.channels" :key="c.id"
                                class="inline-flex items-center gap-2 px-3 py-1.5 border rounded-md cursor-pointer">
                                <input type="checkbox" :value="c.id" v-model="form.channel_ids" />
                                <span class="text-sm">{{ c.name }} <span class="text-gray-400">({{ c.provider }})</span></span>
                            </label>
                        </div>
                        <p v-else class="mt-2 text-sm text-gray-500">No channels in this workspace yet.</p>
                    </div>

                    <div>
                        <InputLabel for="content" value="Content" />
                        <textarea id="content" v-model="form.content" rows="6"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            placeholder="What do you want to share?"></textarea>
                        <p class="mt-1 text-xs text-gray-400">{{ characters }} characters</p>
                    </div>

                    <div>
                        <InputLabel for="scheduled_at" value="Schedule for (leave empty to save as draft or use the queue)" />
                        <input id="scheduled_at" type="datetime-local" v-model="form.scheduled_at"
                            :disabled="form.use_queue"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100" />
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" v-model="form.use_queue" />
                            <span class="text-sm text-gray-700">Add to queue (next best time slot)</span>
                        </label>
                        <div class="inline-flex items-center gap-2">
                            <span class="text-sm text-gray-700">Repeat:</span>
                            <select v-model="form.recurring_rule" class="border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Never</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing || form.channel_ids.length === 0">
                            {{ (form.scheduled_at || form.use_queue) ? 'Schedule post' : 'Save draft' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
