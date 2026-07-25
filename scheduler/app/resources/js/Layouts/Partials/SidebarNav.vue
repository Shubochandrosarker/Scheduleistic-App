<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandMark from '@/Layouts/Partials/BrandMark.vue';

const emit = defineEmits(['navigate', 'logout']);

const page = usePage();

const branding = computed(() => page.props.branding ?? {});
const usage = computed(() => page.props.planUsage ?? null);
const plan = computed(() => page.props.planName ?? null);

// Single source of truth for the rail. `match` is the ziggy route pattern that
// keeps an item lit while you're anywhere inside that section.
const items = computed(() => {
    const nav = [
        { label: 'Overview', route: 'dashboard', match: 'dashboard', d: 'M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z' },
        { label: 'Calendar', route: 'posts.index', match: 'posts.index', d: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4' },
        { label: 'Composer', route: 'posts.create', match: 'posts.create', d: 'M4 20h16M6 16l10-10 3 3-10 10H6z' },
        { label: 'Clients', route: 'workspaces.index', match: 'workspaces.*', d: 'M16 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M9.5 6.5a3 3 0 11-6 0 3 3 0 016 0M21 20v-2a4 4 0 00-3-3.9M16 6.6a3 3 0 010 5.8' },
        { label: 'Analytics', route: 'analytics.index', match: 'analytics.*', d: 'M4 20V10M10 20V4M16 20v-7M22 20H2' },
        { label: 'White-label', route: 'branding.edit', match: 'branding.*', d: 'M4 7h16M4 12h16M4 17h16M9 5v4M15 10v4M7 15v4' },
        { label: 'Billing', route: 'billing.index', match: 'billing.*', d: 'M3 7h18v11H3zM3 11h18' },
    ];

    if (page.props.isPlatformAdmin) {
        nav.push({
            label: 'Control plane',
            route: 'admin.organizations.index',
            match: 'admin.*',
            d: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
        });
    }

    return nav.map((item) => ({
        ...item,
        href: route(item.route),
        active: route().current(item.match),
    }));
});

// Posts-published is the meter that actually gates the account, so it's the one
// the rail shows. Hidden entirely if the controller didn't share usage.
const postsMeter = computed(() => {
    const meter = usage.value?.monthly_posts;
    if (!meter) return null;

    const unlimited = meter.limit === -1;

    return {
        usage: meter.usage,
        limit: unlimited ? '∞' : meter.limit,
        pct: unlimited ? 8 : Math.min(100, Math.round((meter.usage / Math.max(meter.limit, 1)) * 100)),
    };
});
</script>

<template>
    <aside
        class="sc-scroll sticky top-0 max-h-screen min-h-screen w-[254px] shrink-0 flex-col gap-6 overflow-y-auto border-r p-4"
        style="background: var(--nav-bg); border-color: var(--line)"
    >
        <Link :href="route('dashboard')" class="flex items-center gap-2.5 px-1.5 pt-1" @click="emit('navigate')">
            <BrandMark />
            <span class="min-w-0">
                <span class="block truncate text-[15px] font-extrabold tracking-[-0.02em] text-t1">
                    {{ branding.name || 'Scheduleistic' }}
                </span>
                <span class="block text-[10px] font-bold uppercase tracking-[0.14em] text-t4">
                    {{ plan ? `${plan} edition` : 'Agency edition' }}
                </span>
            </span>
        </Link>

        <nav class="flex flex-col gap-0.5">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="flex items-center gap-3 rounded-xl border px-3 py-2.5 text-[13.5px] transition-colors duration-150"
                :style="item.active
                    ? 'background: var(--sc-soft); border-color: var(--sc-mid); color: var(--t1); font-weight: 700'
                    : 'background: transparent; border-color: transparent; color: var(--t3); font-weight: 600'"
                :class="item.active ? '' : 'hover:!bg-[color:var(--line)] hover:!text-t1'"
                @click="emit('navigate')"
            >
                <svg
                    width="17"
                    height="17"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="shrink-0 opacity-90"
                >
                    <path :d="item.d" />
                </svg>
                <span class="whitespace-nowrap">{{ item.label }}</span>
            </Link>
        </nav>

        <div class="mt-auto flex flex-col gap-2.5">
            <div class="sc-card p-3.5">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <span class="sc-eyebrow truncate">{{ plan || 'Current' }} plan</span>
                    <span v-if="postsMeter" class="shrink-0 text-[11px] font-semibold text-t3">
                        {{ postsMeter.usage }} / {{ postsMeter.limit }}
                    </span>
                </div>

                <div v-if="postsMeter" class="h-1.5 overflow-hidden rounded-full" style="background: var(--line)">
                    <div
                        class="h-full rounded-full"
                        :style="{
                            width: postsMeter.pct + '%',
                            background: 'linear-gradient(90deg, var(--sc-accent), var(--sc-accent-2))',
                        }"
                    ></div>
                </div>

                <p class="mt-2.5 text-[11.5px] leading-snug text-t3">
                    {{ postsMeter ? 'Posts this cycle.' : 'Manage your plan and invoices.' }}
                </p>

                <Link
                    :href="route('billing.index')"
                    class="sc-btn sc-btn-sm sc-btn-secondary mt-3 w-full"
                    @click="emit('navigate')"
                >
                    Manage plan →
                </Link>
            </div>

            <button
                type="button"
                class="px-2 py-1 text-left text-[12px] font-semibold text-t4 transition-colors hover:text-t2"
                @click="emit('logout')"
            >
                ↩ Sign out
            </button>
        </div>
    </aside>
</template>
