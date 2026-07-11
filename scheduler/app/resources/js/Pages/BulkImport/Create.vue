<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    workspace: Object,
    channels: Array,
});

const form = useForm({
    file: null,
});

const onFileChange = (event) => {
    form.file = event.target.files[0] ?? null;
};

const submit = () => form.post(route('workspaces.import', props.workspace.id), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});
</script>

<template>
    <AppLayout :title="`Bulk import — ${workspace.name}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Bulk import — {{ workspace.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">CSV format</h3>
                    <p class="text-sm text-gray-500 mb-3">
                        One row per post, with a header row. <code class="bg-gray-100 px-1 rounded">content</code>
                        is required; leave <code class="bg-gray-100 px-1 rounded">scheduled_at</code> empty to add the
                        post to the queue instead of an exact time.
                    </p>
                    <pre class="bg-gray-50 border rounded-md p-4 text-xs overflow-x-auto text-gray-700"><code>content,scheduled_at,providers
"Kicking off our spring sale!",2026-06-10 09:00:00,linkedin|facebook
"Behind the scenes at the studio",,instagram</code></pre>
                    <p class="text-sm text-gray-500 mt-3">
                        <span class="font-medium text-gray-700">providers</span> is optional and pipe-separated
                        (<code class="bg-gray-100 px-1 rounded">|</code>) — leave it empty to post to every connected
                        channel. Channels in this workspace:
                    </p>
                    <p class="mt-1 flex flex-wrap gap-2">
                        <span v-if="channels.length === 0" class="text-sm text-gray-400">
                            No channels connected yet — connect one before importing.
                        </span>
                        <code v-for="c in channels" :key="c.id" class="bg-gray-100 px-1.5 py-0.5 rounded text-xs text-gray-700">
                            {{ c.provider }}
                        </code>
                    </p>
                </div>

                <form @submit.prevent="submit" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <div>
                        <InputLabel for="file" value="CSV file" />
                        <input id="file" type="file" accept=".csv,text/csv" @change="onFileChange"
                            class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" required />
                        <InputError :message="form.errors.file" class="mt-1" />
                    </div>
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing || !form.file">
                            {{ form.processing ? 'Importing…' : 'Import posts' }}
                        </PrimaryButton>
                    </div>
                </form>

                <Link :href="route('workspaces.index')" class="text-indigo-600 hover:underline text-sm">
                    ← Back to workspaces
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
