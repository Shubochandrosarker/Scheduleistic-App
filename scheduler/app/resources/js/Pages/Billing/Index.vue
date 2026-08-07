<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import MeterBar from '@/Components/UI/MeterBar.vue';

const props = defineProps({
    currentPlan: String,
    basePlan: String,
    hasActiveOverride: Boolean,
    override: Object,
    plans: Array,
    usage: Object,
    subscribed: Boolean,
});

const subscribe = (plan) => router.post(route('billing.checkout', plan));
const portal = () => (window.location = route('billing.portal'));

const label = (v) => (v === -1 ? 'Unlimited' : v);

// Each plan card gets its own accent so the tiers read as a ladder rather than
// four identical boxes, and the current one is visually pinned.
const TIER_TONES = ['var(--t3)', 'var(--cy-text)', 'var(--sc-accent-text)', 'var(--alt)'];

const cards = computed(() => props.plans.map((p, i) => {
    const current = p.key === props.currentPlan;
    const index = props.plans.findIndex((x) => x.key === props.currentPlan);

    return {
        ...p,
        current,
        accent: TIER_TONES[i % TIER_TONES.length],
        features: [
            `${label(p.limits.workspaces)} workspaces`,
            `${label(p.limits.channels)} channels`,
            `${label(p.limits.members)} team members`,
            `${label(p.limits.monthly_posts)} posts / month`,
            ...(p.features?.ai_captions ? ['AI captions'] : []),
            ...(p.features?.client_approval ? ['Client approvals'] : []),
            ...(p.features?.white_label ? ['White-label + custom domain'] : []),
        ],
        // Purchasable tiers below your current one read as a downgrade.
        cta: current ? 'Current plan' : (i < index ? 'Downgrade' : 'Upgrade →'),
    };
}));

const meterTones = { monthly_posts: 'accent', workspaces: 'alt', channels: 'cyan', members: 'ok' };

const titleCase = (v) => (v ? v.charAt(0).toUpperCase() + v.slice(1) : '—');

const planLabel = computed(() => titleCase(props.currentPlan));
const basePlanLabel = computed(() => titleCase(props.basePlan));

const overrideExpiry = computed(() => (props.override?.expires_at
    ? new Date(props.override.expires_at).toLocaleDateString(undefined, { dateStyle: 'medium' })
    : null));
</script>

<template>
    <AppLayout title="Billing">
        <PageHeader eyebrow="Subscription">
            <template #title>Billing &amp; plans</template>
            <template #subtitle>
                Metered tiers, enforced at the query layer. Change plan here and the new limits
                apply on the next request — no deploy.
            </template>
            <template #actions>
                <SBadge tone="accent">{{ planLabel }} plan</SBadge>
                <SButton v-if="subscribed" @click="portal">Manage subscription</SButton>
            </template>
        </PageHeader>

        <!-- A platform-admin override always shows alongside the base plan,
             so a temporary adjustment never reads as a silent Stripe change. -->
        <div
            v-if="hasActiveOverride"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border px-4 py-3 text-[12.5px] font-semibold"
            style="background: rgba(167,139,250,0.12); border-color: rgba(167,139,250,0.3); color: var(--alt)"
        >
            <span>
                Your plan has been adjusted to <strong>{{ planLabel }}</strong> by the platform
                (your billed plan is <strong>{{ basePlanLabel }}</strong>).
                <template v-if="override?.reason">Reason: {{ override.reason }}.</template>
                <template v-if="overrideExpiry">Expires {{ overrideExpiry }}.</template>
                <template v-else>No expiration set.</template>
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="p in cards"
                :key="p.key"
                class="relative flex flex-col rounded-2xl border p-5"
                :style="p.current
                    ? 'background: linear-gradient(180deg, var(--sc-soft), var(--card-bg2)); border-color: var(--sc-mid); box-shadow: 0 16px 48px var(--shadow-d)'
                    : 'background: var(--card-bg); border-color: var(--card-line); box-shadow: var(--card-shadow)'"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.12em]" :style="{ color: p.accent }">
                        {{ p.name }}
                    </p>
                    <SBadge v-if="p.current" tone="accent">Current</SBadge>
                </div>

                <p class="mt-3 text-[34px] font-extrabold leading-none tracking-[-0.03em] text-t1">
                    ${{ p.price }}<span class="ml-1 text-[13px] font-semibold text-t4">/mo</span>
                </p>

                <ul class="mt-5 flex-1 space-y-2">
                    <li v-for="f in p.features" :key="f" class="flex items-start gap-2 text-[12.5px] text-t2">
                        <span class="mt-0.5 shrink-0 font-extrabold" :style="{ color: p.accent }">✓</span>
                        <span>{{ f }}</span>
                    </li>
                </ul>

                <SButton
                    v-if="!p.current && p.key !== 'free'"
                    class="mt-5 w-full"
                    variant="primary"
                    @click="subscribe(p.key)"
                >{{ p.cta }}</SButton>

                <SButton v-else class="mt-5 w-full" variant="secondary" disabled>
                    {{ p.current ? 'Current plan' : 'Free tier' }}
                </SButton>
            </article>
        </div>

        <SCard class="mt-4" title="This cycle" subtitle="What your organization has consumed against its plan.">
            <div class="grid gap-5 sm:grid-cols-2">
                <MeterBar
                    v-for="(meter, key) in usage"
                    :key="key"
                    :label="String(key).replace(/_/g, ' ')"
                    :usage="meter.usage"
                    :limit="meter.limit"
                    :tone="meterTones[key] || 'accent'"
                />
            </div>
        </SCard>
    </AppLayout>
</template>
