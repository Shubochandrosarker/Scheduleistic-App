<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandMark from '@/Layouts/Partials/BrandMark.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import { NAV_GROUPS } from '@/navigation';
import { useCapabilities, useTerms } from '@/composables/useCapabilities';
import { useSidebar } from '@/composables/useSidebar';

const props = defineProps({
    /** Force the expanded rail (the mobile drawer never collapses). */
    forceExpanded: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate', 'logout']);

const page = usePage();
const { can, upgradeFor } = useCapabilities();
const { many } = useTerms();
const { compact, toggleCompact } = useSidebar();

const branding = computed(() => page.props.branding ?? {});
const usage = computed(() => page.props.planUsage ?? null);
const plan = computed(() => page.props.planName ?? null);

const isCompact = computed(() => props.forceExpanded ? false : compact.value);

/**
 * Resolve a route safely. A nav item pointing at a route that does not exist
 * in this build (an optional Jetstream feature, say) is dropped rather than
 * rendered as a link that throws on click.
 */
const hrefFor = (item) => {
    if (!item.route) return null;

    try {
        if (!route().has(item.route)) return null;

        return item.param === 'currentTeam'
            ? route(item.route, page.props.auth?.user?.current_team_id)
            : route(item.route);
    } catch {
        return null;
    }
};

const groups = computed(() => NAV_GROUPS
    .filter((group) => !group.admin || page.props.isPlatformAdmin)
    .map((group) => ({
        label: group.label,
        items: group.items
            .filter((item) => !item.admin || page.props.isPlatformAdmin)
            .map((item) => {
                const href = hrefFor(item);
                const locked = item.capability ? !can(item.capability) : false;

                return {
                    ...item,
                    // The nav uses the customer-facing noun where one exists.
                    label: item.term ? many(item.term) : item.label,
                    href,
                    locked,
                    // Locked and unbuilt items always say why. The brief
                    // forbids a disabled item with no explanation.
                    note: locked
                        ? `${upgradeFor(item.capability)} plan and above`
                        : (item.soon ? 'On the roadmap' : null),
                    active: href ? route().current(item.match) : false,
                    badge: item.badge ? Number(page.props[item.badge] ?? 0) : 0,
                };
            })
            // Drop items whose route does not exist and which are not
            // deliberately roadmap placeholders.
            .filter((item) => item.href || item.soon || item.locked),
    }))
    .filter((group) => group.items.length > 0));

// Posts-published is the meter that actually gates the account.
const postsMeter = computed(() => {
    const meter = usage.value?.monthly_posts;
    if (!meter) return null;

    const unlimited = meter.limit === -1;

    return {
        usage: meter.usage,
        limit: unlimited ? 'Unlimited' : meter.limit,
        pct: unlimited ? 8 : Math.min(100, Math.round((meter.usage / Math.max(meter.limit, 1)) * 100)),
    };
});
</script>

<template>
    <aside
        class="sc-scroll sticky top-0 max-h-screen min-h-screen shrink-0 flex-col overflow-y-auto border-r"
        :class="isCompact ? 'w-[68px] px-2 py-4' : 'w-[254px] p-4'"
        style="background: var(--nav-bg); border-color: var(--line)"
        aria-label="Main navigation"
    >
        <Link
            :href="route('dashboard')"
            class="mb-5 flex items-center gap-2.5 rounded-lg px-1.5 pt-1"
            :class="isCompact ? 'justify-center' : ''"
            @click="emit('navigate')"
        >
            <BrandMark />
            <span v-if="!isCompact" class="min-w-0">
                <span class="block truncate text-[15px] font-extrabold tracking-[-0.02em] text-t1">
                    {{ branding.name || 'Scheduleistic' }}
                </span>
                <span class="block text-[12px] font-semibold text-t4">
                    {{ plan ? `${plan} plan` : 'Agency edition' }}
                </span>
            </span>
        </Link>

        <nav class="flex flex-1 flex-col gap-4">
            <div v-for="group in groups" :key="group.label" class="flex flex-col gap-0.5">
                <p
                    v-if="!isCompact"
                    class="px-3 pb-1 text-[12px] font-bold text-t4"
                >
                    {{ group.label }}
                </p>
                <!-- In compact mode the group label becomes a divider, so the
                     grouping survives without the text. -->
                <div v-else class="mx-2 my-1 border-t" style="border-color: var(--line)" role="separator" :aria-label="group.label"></div>

                <template v-for="item in group.items" :key="item.label">
                    <Link
                        v-if="item.href && !item.locked"
                        :href="item.href"
                        class="sc-nav-item"
                        :class="[item.active ? 'sc-nav-item-active' : '', isCompact ? 'justify-center !px-0' : '']"
                        :aria-current="item.active ? 'page' : undefined"
                        :title="isCompact ? item.label : undefined"
                        @click="emit('navigate')"
                    >
                        <SIcon :name="item.icon" :size="18" :label="isCompact ? item.label : null" />
                        <span v-if="!isCompact" class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        <span
                            v-if="item.badge > 0"
                            class="sc-badge-count"
                            :aria-label="`${item.badge} unread`"
                        >{{ item.badge > 99 ? '99+' : item.badge }}</span>
                    </Link>

                    <!-- Locked or not-yet-built: rendered, explained, inert. -->
                    <span
                        v-else
                        class="sc-nav-item cursor-default opacity-60"
                        :class="isCompact ? 'justify-center !px-0' : ''"
                        :title="`${item.label} — ${item.note}`"
                    >
                        <SIcon :name="item.icon" :size="18" :label="isCompact ? `${item.label}, ${item.note}` : null" />
                        <span v-if="!isCompact" class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        <SIcon v-if="!isCompact" name="lock" :size="13" :label="item.note" />
                    </span>
                </template>
            </div>
        </nav>

        <div class="mt-6 flex flex-col gap-2.5">
            <div v-if="!isCompact" class="sc-card p-3.5">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <span class="truncate text-[12px] font-bold text-t3">{{ plan || 'Current' }} plan</span>
                    <span v-if="postsMeter" class="shrink-0 text-[12px] font-semibold text-t3">
                        {{ postsMeter.usage }} / {{ postsMeter.limit }}
                    </span>
                </div>

                <div
                    v-if="postsMeter"
                    class="h-1.5 overflow-hidden rounded-full"
                    style="background: var(--line)"
                    role="progressbar"
                    :aria-valuenow="postsMeter.pct"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`Posts used this cycle: ${postsMeter.usage} of ${postsMeter.limit}`"
                >
                    <div
                        class="h-full rounded-full"
                        :style="{ width: postsMeter.pct + '%', background: 'var(--sc-accent)' }"
                    ></div>
                </div>

                <p class="mt-2.5 text-[12.5px] leading-snug text-t3">
                    {{ postsMeter ? 'Posts this cycle.' : 'Manage your plan and invoices.' }}
                </p>

                <Link :href="route('billing.index')" class="sc-btn sc-btn-sm sc-btn-secondary mt-3 w-full" @click="emit('navigate')">
                    Manage plan
                </Link>
            </div>

            <button
                v-if="!forceExpanded"
                type="button"
                class="sc-nav-item"
                :class="isCompact ? 'justify-center !px-0' : ''"
                :aria-pressed="isCompact"
                @click="toggleCompact"
            >
                <SIcon :name="isCompact ? 'chevronRight' : 'chevronLeft'" :size="18" :label="isCompact ? 'Expand navigation' : 'Collapse navigation'" />
                <span v-if="!isCompact">Collapse</span>
            </button>

            <button
                type="button"
                class="sc-nav-item"
                :class="isCompact ? 'justify-center !px-0' : ''"
                @click="emit('logout')"
            >
                <SIcon name="logout" :size="18" :label="isCompact ? 'Sign out' : null" />
                <span v-if="!isCompact">Sign out</span>
            </button>
        </div>
    </aside>
</template>
