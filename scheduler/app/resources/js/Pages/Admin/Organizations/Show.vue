<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import SButton from '@/Components/UI/SButton.vue';
import MeterBar from '@/Components/UI/MeterBar.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    organization: Object,
    owner: Object,
    members: Array,
    workspaces: Array,
    subscription: Object,
    plan: Object,
    usage: Object,
    branding: Object,
    planOptions: Array,
    auditHistory: Object,
});

const fmt = (value) => (value ? new Date(value).toLocaleString() : '—');

// --- Suspend -------------------------------------------------------------
const suspend = () => router.post(route('admin.organizations.suspend', props.organization.id), {}, { preserveScroll: true });

// --- Plan override ---------------------------------------------------------
const showOverrideForm = ref(false);
const overrideForm = ref({ plan: '', reason: '', expires_at: '' });

const submitOverride = () => {
    router.post(route('admin.organizations.plan-override.update', props.organization.id), overrideForm.value, {
        preserveScroll: true,
        onSuccess: () => { showOverrideForm.value = false; overrideForm.value = { plan: '', reason: '', expires_at: '' }; },
    });
};

const removeOverride = () => {
    const reason = window.prompt('Reason for removing this plan override? (required, visible in the audit log)');
    if (!reason) return;

    router.delete(route('admin.organizations.plan-override.destroy', props.organization.id), {
        data: { reason },
        preserveScroll: true,
    });
};

const resyncPlan = () => router.post(route('admin.organizations.resync-plan', props.organization.id), {}, { preserveScroll: true });

// --- Entitlements ----------------------------------------------------------
const showGrantForm = ref(false);
const grantForm = ref({ type: 'capability', key: '', value: '', reason: '' });

const submitGrant = () => {
    router.post(route('admin.organizations.entitlements.grant', props.organization.id), grantForm.value, {
        preserveScroll: true,
        onSuccess: () => { showGrantForm.value = false; grantForm.value = { type: 'capability', key: '', value: '', reason: '' }; },
    });
};

const clearEntitlement = (type, key) => {
    const reason = window.prompt(`Reason for clearing this ${type} override? (required, visible in the audit log)`);
    if (!reason) return;

    router.delete(route('admin.organizations.entitlements.clear', props.organization.id), {
        data: { type, key, reason },
        preserveScroll: true,
    });
};

const grantedCapabilities = () => Object.entries(props.plan.capabilities).filter(([, granted]) => granted).map(([key]) => key);
</script>

<template>
    <AppLayout title="Organization detail">
        <PageHeader eyebrow="Platform admin">
            <template #title>
                {{ organization.name }}
                <SBadge v-if="organization.suspended" tone="bad">Suspended</SBadge>
            </template>
            <template #subtitle>Organization ID {{ organization.id }} · created {{ fmt(organization.created_at) }}</template>
            <template #actions>
                <Link :href="route('admin.organizations.index')" class="sc-btn sc-btn-sm sc-btn-secondary">Back to list</Link>
                <button
                    type="button"
                    class="sc-btn sc-btn-sm"
                    :class="organization.suspended ? 'sc-btn-success' : 'sc-btn-danger'"
                    @click="suspend"
                >{{ organization.suspended ? 'Reactivate' : 'Suspend' }}</button>
            </template>
        </PageHeader>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <!-- Profile + usage -->
            <SCard title="Profile &amp; usage" class="lg:col-span-1">
                <dl class="flex flex-col gap-2.5 text-[13px]">
                    <div class="flex justify-between"><dt class="text-t4">Timezone</dt><dd class="font-semibold text-t1">{{ organization.timezone }}</dd></div>
                    <div class="flex justify-between"><dt class="text-t4">Workspaces</dt><dd class="font-semibold text-t1">{{ organization.workspaces_count }}</dd></div>
                    <div class="flex justify-between"><dt class="text-t4">Onboarded</dt><dd class="font-semibold text-t1">{{ fmt(organization.onboarded_at) }}</dd></div>
                    <div v-if="organization.suspended" class="flex justify-between"><dt class="text-t4">Suspended</dt><dd class="font-semibold text-t1">{{ fmt(organization.suspended_at) }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-col gap-3 border-t pt-3" style="border-color: var(--line)">
                    <MeterBar v-for="(meter, key) in usage" :key="key" :label="key.replace('_', ' ')" :usage="meter.usage" :limit="meter.limit" />
                </div>
            </SCard>

            <!-- Owner + members -->
            <SCard title="Owner &amp; members" class="lg:col-span-1">
                <div v-if="owner" class="mb-3 rounded-lg border p-3" style="border-color: var(--line2); background: var(--s2)">
                    <p class="text-[12.5px] font-bold text-t1">{{ owner.name }} <SBadge tone="accent">Owner</SBadge></p>
                    <p class="text-[11.5px] text-t4">{{ owner.email }}</p>
                </div>
                <ul class="flex flex-col gap-2">
                    <li v-for="member in members" :key="member.id" class="flex items-center justify-between text-[12.5px]">
                        <span class="truncate text-t2">{{ member.name }} <span class="text-t4">({{ member.email }})</span></span>
                        <SBadge tone="neutral" class="capitalize shrink-0">{{ member.role }}</SBadge>
                    </li>
                    <li v-if="!members.length" class="text-[12.5px] text-t4">No additional members.</li>
                </ul>
            </SCard>

            <!-- Workspaces -->
            <SCard title="Workspaces" class="lg:col-span-1">
                <EmptyState v-if="!workspaces.length" icon="layers" title="No workspaces" description="This organization has not created any client workspaces yet." />
                <ul v-else class="flex flex-col gap-2">
                    <li v-for="w in workspaces" :key="w.id" class="flex items-center justify-between text-[12.5px]">
                        <span class="truncate text-t2">{{ w.name }}</span>
                        <span class="shrink-0 text-t4">{{ w.channels_count }} channel{{ w.channels_count === 1 ? '' : 's' }}</span>
                    </li>
                </ul>
            </SCard>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <!-- Subscription -->
            <SCard title="Subscription &amp; billing">
                <template #actions>
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="resyncPlan">Resync base plan</button>
                </template>
                <EmptyState v-if="!subscription" icon="card" title="No Stripe subscription" description="This organization has never subscribed, or is on the free plan only." />
                <dl v-else class="grid grid-cols-2 gap-x-4 gap-y-2 text-[13px]">
                    <dt class="text-t4">Status</dt><dd class="font-semibold capitalize text-t1">{{ subscription.status.replace('_', ' ') }}</dd>
                    <dt class="text-t4">Customer</dt><dd class="truncate font-mono text-[12px] text-t2">{{ subscription.stripe_customer_id }}</dd>
                    <dt class="text-t4">Subscription</dt><dd class="truncate font-mono text-[12px] text-t2">{{ subscription.stripe_subscription_id }}</dd>
                    <dt class="text-t4">Matched plan</dt><dd class="font-semibold text-t1">{{ subscription.matched_plan ?? 'Unrecognized price ID' }}</dd>
                    <dt v-if="subscription.trial_ends_at" class="text-t4">Trial ends</dt><dd v-if="subscription.trial_ends_at" class="text-t1">{{ fmt(subscription.trial_ends_at) }}</dd>
                    <dt v-if="subscription.ends_at" class="text-t4">Ends</dt><dd v-if="subscription.ends_at" class="text-t1">{{ fmt(subscription.ends_at) }}</dd>
                    <dt class="text-t4">Last synced</dt><dd class="text-t1">{{ fmt(subscription.updated_at) }}</dd>
                </dl>
            </SCard>

            <!-- Branding -->
            <SCard title="Branding &amp; custom domain">
                <template #actions>
                    <Link :href="route('branding.edit')" class="text-[11.5px] font-semibold text-t4">Owner branding page</Link>
                </template>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[13px]">
                    <dt class="text-t4">Display name</dt><dd class="font-semibold text-t1">{{ branding.config.name }}</dd>
                    <dt class="text-t4">Custom domain</dt>
                    <dd class="text-t1">
                        {{ branding.custom_domain ?? 'None' }}
                        <SBadge v-if="branding.custom_domain" :tone="branding.custom_domain_verified ? 'ok' : 'warn'" class="ml-1.5">
                            {{ branding.custom_domain_verified ? 'Verified' : 'Unverified' }}
                        </SBadge>
                    </dd>
                </dl>
            </SCard>
        </div>

        <!-- Plan & entitlements -->
        <SCard class="mt-4" title="Effective plan &amp; entitlements">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="sc-eyebrow text-t4">Billed plan</p>
                    <p class="text-[15px] font-bold capitalize text-t1">{{ plan.base }}</p>

                    <p class="sc-eyebrow mt-3 text-t4">Effective plan</p>
                    <p class="text-[15px] font-bold capitalize text-t1">
                        {{ plan.effective }}
                        <SBadge v-if="plan.has_active_override" tone="accent">Admin granted</SBadge>
                    </p>

                    <div v-if="plan.override.plan" class="mt-3 rounded-lg border p-3 text-[12.5px]" style="border-color: var(--line2); background: var(--s2)">
                        <p class="text-t2"><span class="font-semibold text-t1 capitalize">{{ plan.override.plan }}</span> granted by {{ plan.override.set_by?.name ?? 'unknown' }}</p>
                        <p class="mt-1 text-t4">Reason: {{ plan.override.reason }}</p>
                        <p class="mt-1 text-t4">Expires: {{ plan.override.expires_at ? fmt(plan.override.expires_at) : 'Never' }}</p>
                        <button type="button" class="sc-btn sc-btn-sm sc-btn-danger mt-2" @click="removeOverride">Remove override</button>
                    </div>

                    <SButton v-else size="sm" class="mt-3" @click="showOverrideForm = !showOverrideForm">Grant plan override</SButton>

                    <form v-if="showOverrideForm" class="mt-3 flex flex-col gap-2 rounded-lg border p-3" style="border-color: var(--line2)" @submit.prevent="submitOverride">
                        <select v-model="overrideForm.plan" required class="sc-input !py-2 !text-[12.5px]">
                            <option value="" disabled>Grant which plan?</option>
                            <option v-for="p in planOptions" :key="p.key" :value="p.key">{{ p.name }}</option>
                        </select>
                        <input v-model="overrideForm.reason" required type="text" maxlength="500" placeholder="Reason (required)" class="sc-input !py-2 !text-[12.5px]" />
                        <label class="text-[11.5px] text-t4">
                            Expires (optional)
                            <input v-model="overrideForm.expires_at" type="datetime-local" class="sc-input mt-1 !py-2 !text-[12.5px]" />
                        </label>
                        <div class="flex gap-2">
                            <SButton size="sm" type="submit">Grant override</SButton>
                            <SButton size="sm" variant="ghost" type="button" @click="showOverrideForm = false">Cancel</SButton>
                        </div>
                        <p class="text-[11px] text-t4">Requires re-confirming your password.</p>
                    </form>
                </div>

                <div>
                    <p class="sc-eyebrow text-t4">Entitlement grants (raise/grant only)</p>
                    <ul class="mt-2 flex flex-col gap-1.5">
                        <li v-for="(value, key) in (plan.entitlement_overrides.capabilities ?? {})" :key="`cap-${key}`" class="flex items-center justify-between text-[12.5px]">
                            <span class="text-t2">{{ key }} <span class="text-t4">(capability)</span></span>
                            <button type="button" class="text-[11px] font-semibold text-t4 hover:text-bad" @click="clearEntitlement('capability', key)">Clear</button>
                        </li>
                        <li v-for="(value, key) in (plan.entitlement_overrides.limits ?? {})" :key="`limit-${key}`" class="flex items-center justify-between text-[12.5px]">
                            <span class="text-t2">{{ key }} <span class="text-t4">→ {{ value }}</span></span>
                            <button type="button" class="text-[11px] font-semibold text-t4 hover:text-bad" @click="clearEntitlement('limit', key)">Clear</button>
                        </li>
                        <li v-if="!Object.keys(plan.entitlement_overrides.capabilities ?? {}).length && !Object.keys(plan.entitlement_overrides.limits ?? {}).length" class="text-[12.5px] text-t4">
                            No entitlement overrides — the organization uses its plan's defaults.
                        </li>
                    </ul>

                    <SButton size="sm" class="mt-3" @click="showGrantForm = !showGrantForm">Add entitlement grant</SButton>

                    <form v-if="showGrantForm" class="mt-3 flex flex-col gap-2 rounded-lg border p-3" style="border-color: var(--line2)" @submit.prevent="submitGrant">
                        <select v-model="grantForm.type" class="sc-input !py-2 !text-[12.5px]">
                            <option value="capability">Capability (grant)</option>
                            <option value="limit">Limit (raise)</option>
                        </select>
                        <input v-model="grantForm.key" required type="text" placeholder="Key, e.g. white_label or workspaces" class="sc-input !py-2 !text-[12.5px]" />
                        <input v-if="grantForm.type === 'limit'" v-model="grantForm.value" required type="number" placeholder="New limit value (-1 = unlimited)" class="sc-input !py-2 !text-[12.5px]" />
                        <input v-model="grantForm.reason" required type="text" maxlength="500" placeholder="Reason (required)" class="sc-input !py-2 !text-[12.5px]" />
                        <div class="flex gap-2">
                            <SButton size="sm" type="submit">Grant</SButton>
                            <SButton size="sm" variant="ghost" type="button" @click="showGrantForm = false">Cancel</SButton>
                        </div>
                    </form>
                </div>
            </div>
        </SCard>

        <!-- Audit history -->
        <SCard class="mt-4" flush title="Audit history">
            <EmptyState v-if="!auditHistory.data.length" icon="shield" title="No audit events" description="Privileged actions on this organization will appear here." />
            <ul v-else class="divide-y" style="border-color: var(--line)">
                <li v-for="event in auditHistory.data" :key="event.id" class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-bold capitalize text-t1">{{ event.action.replace(/[._]/g, ' ') }}</p>
                        <p class="truncate text-[11.5px] text-t4">{{ event.actor?.name ?? 'System' }}</p>
                    </div>
                    <span class="shrink-0 text-[11.5px] text-t3">{{ fmt(event.created_at) }}</span>
                </li>
            </ul>
            <nav v-if="auditHistory.links?.length > 3" class="flex flex-wrap justify-center gap-1 p-4" aria-label="Pagination">
                <template v-for="link in auditHistory.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-state preserve-scroll class="sc-pill" :class="link.active ? 'sc-pill-on' : ''" v-html="link.label" />
                    <span v-else class="sc-pill opacity-40" v-html="link.label" />
                </template>
            </nav>
        </SCard>
    </AppLayout>
</template>
