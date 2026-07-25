<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import NetworkChip from '@/Components/UI/NetworkChip.vue';
import { network } from '@/networks';

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

// One card per supported network, carrying whichever channel (if any) is
// already connected for it — so "connected" and "available" read as one grid
// rather than two disconnected lists.
const cards = computed(() => props.providers.map((provider) => {
    const connected = props.channels.filter((c) => c.provider === provider.key);

    return {
        ...provider,
        connected,
        // OAuth providers are only actionable once the instance has credentials.
        blocked: provider.type === 'oauth' && !provider.configured,
    };
}));

const connectedCount = computed(() => props.channels.length);
</script>

<template>
    <AppLayout :title="`Channels — ${workspace.name}`">
        <PageHeader eyebrow="Connected accounts">
            <template #title>Channels · {{ workspace.name }}</template>
            <template #subtitle>
                {{ providers.length }} networks behind one contract.
                {{ connectedCount }} connected for this client.
            </template>
            <template #actions>
                <SButton :href="route('workspaces.index')">← All clients</SButton>
            </template>
        </PageHeader>

        <div class="mb-5 flex flex-wrap items-center gap-5 text-[12px] text-t3">
            <span class="flex items-center gap-1.5">
                <span class="size-1.5 rounded-full" style="background: var(--ok)"></span> Connected
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-1.5 rounded-full" style="background: var(--t4)"></span> Not connected
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-1.5 rounded-full" style="background: var(--warn)"></span> Needs credentials on this instance
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="p in cards" :key="p.key" class="sc-card flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <NetworkChip :provider="p.key" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-bold text-t1">{{ p.label }}</p>
                            <p class="truncate text-[11.5px] text-t4">
                                {{ p.connected.length ? p.connected.map((c) => c.name).join(', ') : 'Not connected' }}
                            </p>
                        </div>
                    </div>

                    <span
                        class="mt-1.5 size-2 shrink-0 rounded-full"
                        :style="{ background: p.connected.length ? 'var(--ok)' : (p.blocked ? 'var(--warn)' : 'var(--t4)') }"
                    ></span>
                </div>

                <div class="mt-4 flex items-center justify-between border-t pt-3 text-[11.5px] text-t3" style="border-color: var(--line)">
                    <span>{{ p.connected.length ? 'Healthy' : '—' }}</span>
                    <span>{{ network(p.key).limit.toLocaleString() }} char cap</span>
                </div>

                <!-- Connected accounts for this network -->
                <ul v-if="p.connected.length" class="mt-3 space-y-1.5">
                    <li
                        v-for="c in p.connected"
                        :key="c.id"
                        class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2"
                        style="background: var(--s2)"
                    >
                        <span class="min-w-0 truncate text-[12px] text-t2">{{ c.name }}</span>
                        <button
                            type="button"
                            class="shrink-0 text-[11.5px] font-bold transition-opacity hover:opacity-70"
                            style="color: var(--bad)"
                            @click="disconnect(c.id)"
                        >Disconnect</button>
                    </li>
                </ul>

                <div class="mt-4">
                    <!-- OAuth, credentials present -->
                    <SButton
                        v-if="p.type === 'oauth' && p.configured"
                        external
                        :href="route('workspaces.channels.connect', [workspace.id, p.key])"
                        :variant="p.connected.length ? 'secondary' : 'primary'"
                        class="w-full"
                    >{{ p.connected.length ? 'Connect another' : 'Connect →' }}</SButton>

                    <!-- OAuth, no credentials on this instance. Existing channels keep
                         working; only connecting a new one needs the app credentials. -->
                    <SButton
                        v-else-if="p.type === 'oauth'"
                        variant="secondary"
                        disabled
                        class="w-full"
                        title="Set this provider's OAuth credentials in the environment to connect accounts"
                    >{{ p.connected.length ? 'Can’t add — no OAuth app' : 'Not configured' }}</SButton>

                    <!-- Token providers: a credential form instead of a redirect -->
                    <SButton
                        v-else
                        :variant="openForm === p.key ? 'secondary' : (p.connected.length ? 'secondary' : 'primary')"
                        class="w-full"
                        @click="toggleForm(p)"
                    >{{ openForm === p.key ? 'Cancel' : (p.connected.length ? 'Add another' : 'Connect →') }}</SButton>
                </div>

                <form
                    v-if="openForm === p.key"
                    class="mt-4 space-y-3 border-t pt-4"
                    style="border-color: var(--line)"
                    @submit.prevent="submitToken(p)"
                >
                    <div v-for="f in p.fields" :key="f.key">
                        <label class="sc-label">{{ f.label }}</label>
                        <input
                            v-model="tokenForm[f.key]"
                            :type="f.type === 'password' ? 'password' : 'text'"
                            class="sc-input"
                            required
                        />
                    </div>
                    <SButton type="submit" variant="primary" class="w-full">Save {{ p.label }}</SButton>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
