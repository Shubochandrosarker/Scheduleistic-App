<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SIcon from '@/Components/UI/SIcon.vue';
import { MOBILE_NAV } from '@/navigation';

/**
 * Mobile bottom navigation.
 *
 * Four destinations plus "More" (which opens the full drawer), with Compose
 * raised in the centre because creating a post is the reason people open this
 * on a phone. Sits above the iOS home indicator via env(safe-area-inset-bottom).
 */
const emit = defineEmits(['more']);

const page = usePage();

const items = computed(() => MOBILE_NAV.map((item) => ({
    ...item,
    href: route().has(item.route) ? route(item.route) : null,
    active: route().current(item.match),
    badge: item.badge ? Number(page.props[item.badge] ?? 0) : 0,
})).filter((item) => item.href));
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 flex items-stretch justify-around border-t lg:hidden"
        style="background: var(--head-bg); border-color: var(--line); backdrop-filter: blur(16px);
               padding-bottom: env(safe-area-inset-bottom, 0px)"
        aria-label="Primary"
    >
        <template v-for="item in items" :key="item.label">
            <Link
                v-if="!item.primary"
                :href="item.href"
                class="relative flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 px-1 py-2 text-[12px] font-semibold"
                :style="{ color: item.active ? 'var(--sc-accent-text)' : 'var(--t3)' }"
                :aria-current="item.active ? 'page' : undefined"
            >
                <SIcon :name="item.icon" :size="20" />
                <span>{{ item.label }}</span>
                <span
                    v-if="item.badge > 0"
                    class="absolute right-[22%] top-1.5 rounded-full px-1.5 text-[11px] font-bold leading-[16px] text-white"
                    style="background: var(--bad)"
                    :aria-label="`${item.badge} unread`"
                >{{ item.badge > 9 ? '9+' : item.badge }}</span>
            </Link>

            <!-- Compose: raised, filled, and the widest target on the bar. -->
            <Link
                v-else
                :href="item.href"
                class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 px-1 py-2 text-[12px] font-bold"
                style="color: var(--t2)"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-full"
                    style="background: var(--sc-accent); color: #fff; box-shadow: 0 4px 14px var(--sc-mid)"
                >
                    <SIcon name="plus" :size="22" :stroke-width="2.2" />
                </span>
                <span class="sr-only">{{ item.label }}</span>
            </Link>
        </template>

        <button
            type="button"
            class="flex min-h-[56px] flex-1 flex-col items-center justify-center gap-1 px-1 py-2 text-[12px] font-semibold"
            style="color: var(--t3)"
            @click="emit('more')"
        >
            <SIcon name="menu" :size="20" />
            <span>More</span>
        </button>
    </nav>
</template>
