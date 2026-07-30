<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    endpoints: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    workspaces: { type: Array, default: () => [] },
    deliveries: { type: Array, default: () => [] },
    newSecret: { type: String, default: null },
});

const creating = ref(false);

const form = useForm({
    name: '',
    url: '',
    events: [],
    workspace_id: null,
});

const submit = () => form.post(route('webhooks.store'), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); creating.value = false; },
});

const rotate = (endpoint) => {
    if (!window.confirm('Rotate the signing secret? Deliveries signed with the old secret will start failing verification immediately.')) return;

    router.post(route('webhooks.rotate', endpoint.id), {}, { preserveScroll: true });
};

const toggle = (endpoint) => router.put(route('webhooks.update', endpoint.id), {
    is_active: !endpoint.is_active,
}, { preserveScroll: true });

const replay = (delivery) => router.post(route('webhooks.replay', delivery.id), {}, { preserveScroll: true });

const remove = (endpoint) => {
    if (!window.confirm(`Delete the endpoint “${endpoint.name}”?`)) return;

    router.delete(route('webhooks.destroy', endpoint.id), { preserveScroll: true });
};

const endpointName = (id) => props.endpoints.find((e) => e.id === id)?.name ?? '—';

const when = (value) => value ? new Date(value).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '';
</script>

<template>
    <AppLayout title="Webhooks">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="sc-title">Webhooks</h1>
                    <p class="sc-sub">Signed outbound events for your own automations.</p>
                </div>
                <button type="button" class="sc-btn sc-btn-primary" @click="creating = !creating">
                    <SIcon name="plus" :size="15" /> New endpoint
                </button>
            </div>

            <!-- The plaintext secret is shown exactly once. -->
            <div v-if="newSecret" class="sc-card p-4" style="border-color: var(--sc-mid)">
                <p class="mb-1 text-[13px] font-bold text-t1">Copy your signing secret now</p>
                <p class="mb-2 text-[13px] text-t3">
                    This is the only time it is shown. It is encrypted at rest and cannot be retrieved again.
                </p>
                <code class="sc-code block break-all p-2">{{ newSecret }}</code>
            </div>

            <form v-if="creating" class="sc-card flex flex-col gap-3 p-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="sc-label" for="w-name">Name</label>
                        <input id="w-name" v-model="form.name" class="sc-input" required maxlength="80" />
                        <p v-if="form.errors.name" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="sc-label" for="w-url">Endpoint URL (HTTPS)</label>
                        <input id="w-url" v-model="form.url" type="url" class="sc-input" required placeholder="https://example.com/hooks/scheduleistic" />
                        <p v-if="form.errors.url" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.url }}</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="sc-label">Events</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="event in events" :key="event" class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-[13px]"
                               style="border-color: var(--line2)">
                            <input v-model="form.events" type="checkbox" :value="event" class="sc-check" />
                            {{ event }}
                        </label>
                    </div>
                    <p v-if="form.errors.events" class="mt-1 text-[12.5px]" style="color: var(--bad)">{{ form.errors.events }}</p>
                </fieldset>

                <div class="flex gap-2">
                    <button type="submit" class="sc-btn sc-btn-primary sc-btn-sm" :disabled="form.processing">Create endpoint</button>
                    <button type="button" class="sc-btn sc-btn-ghost sc-btn-sm" @click="creating = false">Cancel</button>
                </div>
            </form>

            <div v-if="endpoints.length" class="sc-card overflow-hidden">
                <article v-for="endpoint in endpoints" :key="endpoint.id" class="sc-row flex flex-wrap items-start gap-3 p-3.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-[14px] font-bold text-t1">{{ endpoint.name }}</p>
                        <p class="truncate text-[12.5px] text-t3">{{ endpoint.url }}</p>
                        <p class="mt-1 text-[12.5px] text-t3">{{ (endpoint.events ?? []).join(', ') }}</p>
                        <p v-if="endpoint.disabled_reason" class="mt-1 text-[12.5px]" style="color: var(--bad)">
                            {{ endpoint.disabled_reason }}
                        </p>
                    </div>

                    <div class="text-[12.5px] text-t3">
                        <p>{{ endpoint.deliveries_count }} deliveries</p>
                        <p v-if="endpoint.failed_deliveries_count" style="color: var(--bad)">
                            {{ endpoint.failed_deliveries_count }} failed
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-1">
                        <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="toggle(endpoint)">
                            {{ endpoint.is_active ? 'Disable' : 'Enable' }}
                        </button>
                        <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="rotate(endpoint)">
                            <SIcon name="refresh" :size="13" /> Rotate secret
                        </button>
                        <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost !px-2" @click="remove(endpoint)">
                            <SIcon name="trash" :size="14" :label="`Delete ${endpoint.name}`" />
                        </button>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                icon="bolt"
                title="No endpoints yet"
                description="Receive a signed HTTPS POST whenever a post publishes, an approval is requested, or a delivery fails."
            />

            <section v-if="deliveries.length">
                <h2 class="mb-2 text-[15px] font-extrabold text-t1">Recent deliveries</h2>
                <div class="sc-card overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <caption class="sr-only">Recent webhook delivery attempts</caption>
                        <thead>
                            <tr class="sc-row">
                                <th scope="col" class="p-2.5 text-[12px] font-bold text-t4">Endpoint</th>
                                <th scope="col" class="p-2.5 text-[12px] font-bold text-t4">Event</th>
                                <th scope="col" class="p-2.5 text-[12px] font-bold text-t4">Status</th>
                                <th scope="col" class="p-2.5 text-[12px] font-bold text-t4">Attempts</th>
                                <th scope="col" class="p-2.5 text-[12px] font-bold text-t4">When</th>
                                <th scope="col" class="p-2.5 text-[12px] font-bold text-t4"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="delivery in deliveries" :key="delivery.id" class="sc-row">
                                <td class="p-2.5 text-t2">{{ endpointName(delivery.webhook_endpoint_id) }}</td>
                                <td class="p-2.5 text-t2">{{ delivery.event }}</td>
                                <td class="p-2.5 font-semibold"
                                    :style="{ color: delivery.status === 'delivered' ? 'var(--ok)' : delivery.status === 'failed' ? 'var(--bad)' : 'var(--t3)' }">
                                    {{ delivery.status }}
                                    <span v-if="delivery.response_status" class="text-t3">({{ delivery.response_status }})</span>
                                </td>
                                <td class="p-2.5 text-t3">{{ delivery.attempts }}</td>
                                <td class="p-2.5 text-t3">{{ when(delivery.created_at) }}</td>
                                <td class="p-2.5">
                                    <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost" @click="replay(delivery)">
                                        Replay
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
