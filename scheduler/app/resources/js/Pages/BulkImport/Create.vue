<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import NetworkChip from '@/Components/UI/NetworkChip.vue';
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
        <PageHeader eyebrow="Bulk operations">
            <template #title>Import posts from CSV</template>
            <template #subtitle>
                One row per post. Rows with a time get scheduled exactly; rows without one go into
                {{ workspace.name }}'s queue.
            </template>
            <template #actions>
                <SButton :href="route('workspaces.index')">← All clients</SButton>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-[1.3fr_1fr]">
            <SCard title="CSV format" subtitle="A header row, then one row per post.">
                <pre class="sc-scroll overflow-x-auto rounded-xl border p-4 text-[12px] leading-relaxed"
                     style="background: var(--code-bg); border-color: var(--line); color: var(--t2)"><code>content,scheduled_at,providers
"Kicking off our spring sale!",2026-06-10 09:00:00,linkedin|facebook
"Behind the scenes at the studio",,instagram</code></pre>

                <dl class="mt-5 space-y-3 text-[12.5px] leading-relaxed">
                    <div>
                        <dt class="font-bold text-t1"><code class="sc-code">content</code> — required</dt>
                        <dd class="mt-0.5 text-t3">The post body. Quote it if it contains commas.</dd>
                    </div>
                    <div>
                        <dt class="font-bold text-t1"><code class="sc-code">scheduled_at</code> — optional</dt>
                        <dd class="mt-0.5 text-t3">Leave empty to queue the post in the next open slot instead.</dd>
                    </div>
                    <div>
                        <dt class="font-bold text-t1"><code class="sc-code">providers</code> — optional</dt>
                        <dd class="mt-0.5 text-t3">
                            Pipe-separated (<code class="sc-code">|</code>). Empty means every connected channel.
                        </dd>
                    </div>
                </dl>
            </SCard>

            <div class="space-y-4">
                <SCard title="Upload">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="sc-label" for="file">CSV file</label>
                            <input
                                id="file"
                                type="file"
                                accept=".csv,text/csv"
                                class="sc-input cursor-pointer !py-2 !text-[12.5px] file:mr-3 file:cursor-pointer
                                       file:rounded-lg file:border-0 file:bg-[color:var(--sc-soft)] file:px-3
                                       file:py-1.5 file:text-[12px] file:font-bold file:text-[color:var(--sc-accent-text)]"
                                required
                                @change="onFileChange"
                            />
                            <InputError :message="form.errors.file" class="mt-1.5" />
                        </div>

                        <SButton
                            type="submit"
                            variant="primary"
                            class="w-full"
                            :disabled="form.processing || !form.file"
                        >{{ form.processing ? 'Importing…' : 'Import posts' }}</SButton>
                    </form>
                </SCard>

                <SCard title="Channels in this workspace" subtitle="Use these keys in the providers column.">
                    <p v-if="channels.length === 0" class="text-[13px] text-t3">
                        No channels connected yet — connect one before importing.
                    </p>

                    <ul v-else class="space-y-2">
                        <li v-for="c in channels" :key="c.id" class="flex items-center gap-3">
                            <NetworkChip :provider="c.provider" size="sm" />
                            <code class="sc-code">{{ c.provider }}</code>
                        </li>
                    </ul>
                </SCard>
            </div>
        </div>
    </AppLayout>
</template>
