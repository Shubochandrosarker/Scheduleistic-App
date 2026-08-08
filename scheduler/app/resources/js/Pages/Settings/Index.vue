<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import { useCapabilities, useTerms } from '@/composables/useCapabilities';

/**
 * The settings hub the master prompt calls for: one landing page linking
 * every settings-adjacent destination, instead of each only existing as a
 * scattered sidebar entry an owner has to already know about.
 *
 * This page makes no authorization decisions of its own — every card here
 * either always exists (Ziggy's `route().has()` guards a build where it
 * doesn't) or is gated by the same `capabilities` shared prop the sidebar
 * itself uses, so a locked card and a locked nav entry never disagree.
 */
defineProps({
    organization: Object,
    isOwner: Boolean,
});

const page = usePage();
const { can, lockedReason } = useCapabilities();
const { one } = useTerms();

const teamHref = computed(() => {
    if (!route().has('teams.show')) return null;

    return route('teams.show', page.props.auth?.user?.current_team_id);
});

const cards = computed(() => [
    {
        key: 'team',
        icon: 'users',
        title: 'Team',
        description: `Members, roles, and pending invitations for ${page.props.branding?.name ?? 'your organization'}.`,
        href: teamHref.value,
    },
    {
        key: 'billing',
        icon: 'card',
        title: 'Billing & plan',
        description: 'Subscription, usage against your plan\'s limits, and upgrade options.',
        href: route().has('billing.index') ? route('billing.index') : null,
    },
    {
        key: 'branding',
        icon: 'palette',
        title: 'Branding',
        description: `White-label the ${one('workspace').toLowerCase()} experience and connect a custom domain.`,
        href: route().has('branding.edit') ? route('branding.edit') : null,
    },
    {
        key: 'notifications',
        icon: 'bell',
        title: 'Notifications',
        description: 'Choose which events email or notify you, and review recent alerts.',
        href: route().has('notifications.index') ? route('notifications.index') : null,
    },
    {
        key: 'webhooks',
        icon: 'bolt',
        title: 'Webhooks',
        description: 'Send publish and approval events to your own systems.',
        href: can('webhooks') && route().has('webhooks.index') ? route('webhooks.index') : null,
        locked: !can('webhooks'),
        lockedReason: lockedReason('webhooks'),
    },
    {
        key: 'api-tokens',
        icon: 'code',
        title: 'API tokens',
        description: 'Personal access tokens for scripting against the API.',
        href: route().has('api-tokens.index') ? route('api-tokens.index') : null,
    },
    {
        key: 'profile',
        icon: 'lock',
        title: 'Profile & security',
        description: 'Your name and email, password, two-factor authentication, and active sessions.',
        href: route().has('profile.show') ? route('profile.show') : null,
    },
].filter((card) => card.href || card.locked));
</script>

<template>
    <AppLayout title="Settings">
        <PageHeader eyebrow="Organization">
            <template #title>Settings</template>
            <template #subtitle>Everything that configures {{ organization?.name ?? 'your organization' }}, in one place.</template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <component
                :is="card.locked ? 'div' : Link"
                v-for="card in cards"
                :key="card.key"
                :href="card.locked ? undefined : card.href"
                class="sc-card flex items-start gap-3 p-4 transition"
                :class="card.locked ? 'opacity-60' : 'hover:shadow-lg'"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                    style="background: var(--sc-soft); color: var(--sc-accent-text)"
                >
                    <SIcon :name="card.icon" :size="18" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 text-[14px] font-bold text-t1">
                        {{ card.title }}
                        <SIcon v-if="!card.locked" name="chevronRight" :size="14" class="text-t4" />
                    </p>
                    <p class="mt-0.5 text-[12.5px] text-t3">{{ card.description }}</p>
                    <p v-if="card.locked" class="mt-1.5 text-[11.5px] font-semibold" style="color: var(--warn)">
                        {{ card.lockedReason }}
                    </p>
                </div>
            </component>
        </div>

        <p v-if="!isOwner" class="mt-4 text-[12.5px] text-t4">
            Some of these — billing and the danger zone on Team — are only editable by
            {{ organization?.name ?? 'the organization' }}'s owner.
        </p>
    </AppLayout>
</template>
