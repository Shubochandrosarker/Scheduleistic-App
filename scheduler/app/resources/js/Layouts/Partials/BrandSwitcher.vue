<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SIcon from '@/Components/UI/SIcon.vue';
import { useTerms } from '@/composables/useCapabilities';

/**
 * Global brand/client switcher.
 *
 * Filtering the whole planner by brand is the single most common thing an
 * agency does, so it belongs in the top bar rather than buried in a filter
 * panel. Selecting a brand pushes `workspace_ids` into the planner URL, which
 * means the selection is shareable and survives a reload — the same mechanism
 * the filter bar uses, not a parallel one.
 */
const props = defineProps({
    /** [{ id, name, color, channels, health, pending }] */
    brands: { type: Array, default: () => [] },
    /** Currently selected brand ids. */
    selected: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const page = usePage();
const { one, many } = useTerms();

const open = ref(false);
const search = ref('');
const searchInput = ref(null);

const FAVORITES_KEY = 'scheduleistic.brands.favorites';
const RECENT_KEY = 'scheduleistic.brands.recent';

const readList = (key) => {
    try {
        return JSON.parse(window.localStorage.getItem(key) ?? '[]');
    } catch {
        return [];
    }
};

const favorites = ref(readList(FAVORITES_KEY));
const recents = ref(readList(RECENT_KEY));

const persist = (key, value) => {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Storage unavailable — favourites simply do not persist.
    }
};

const matches = computed(() => {
    const term = search.value.trim().toLowerCase();
    const list = props.brands;

    if (!term) return list;

    return list.filter((brand) => brand.name?.toLowerCase().includes(term));
});

/** Favourites first, then recently used, then the rest — all alphabetical. */
const grouped = computed(() => {
    const rank = (brand) => {
        if (favorites.value.includes(brand.id)) return 0;
        if (recents.value.includes(brand.id)) return 1;
        return 2;
    };

    return [...matches.value].sort((a, b) => rank(a) - rank(b) || String(a.name).localeCompare(String(b.name)));
});

const label = computed(() => {
    if (props.selected.length === 0) return `All ${many('workspace').toLowerCase()}`;
    if (props.selected.length === 1) {
        return props.brands.find((b) => b.id === props.selected[0])?.name ?? one('workspace');
    }

    return `${props.selected.length} ${many('workspace').toLowerCase()}`;
});

const toggleFavorite = (brand) => {
    favorites.value = favorites.value.includes(brand.id)
        ? favorites.value.filter((id) => id !== brand.id)
        : [...favorites.value, brand.id];

    persist(FAVORITES_KEY, favorites.value);
};

const select = (brand) => {
    recents.value = [brand.id, ...recents.value.filter((id) => id !== brand.id)].slice(0, 5);
    persist(RECENT_KEY, recents.value);

    emit('select', brand.id);
    open.value = false;
};

const clear = () => {
    emit('select', null);
    open.value = false;
};

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        searchInput.value?.focus();
    } else {
        search.value = '';
    }
});

const healthTone = (health) => ({
    blocked: { color: 'var(--bad)', text: 'Needs attention' },
    warning: { color: 'var(--warn)', text: 'Expiring soon' },
}[health] ?? { color: 'var(--ok)', text: 'Healthy' });
</script>

<template>
    <div class="relative min-w-0">
        <button
            type="button"
            class="flex max-w-[260px] items-center gap-2.5 rounded-xl border px-2.5 py-2 transition-colors"
            style="background: var(--s2); border-color: var(--line2)"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
        >
            <SIcon name="layers" :size="16" />
            <span class="hidden min-w-0 text-left sm:block">
                <span class="block truncate text-[13.5px] font-bold leading-tight text-t1">{{ label }}</span>
                <span class="block text-[12px] leading-tight text-t4">{{ many('workspace') }}</span>
            </span>
            <SIcon name="chevronDown" :size="14" />
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <div
            v-if="open"
            class="absolute left-0 z-50 mt-2 w-[320px] overflow-hidden rounded-xl border shadow-lg"
            style="background: var(--pop-bg); border-color: var(--line2)"
            role="listbox"
        >
            <div class="border-b p-2.5" style="border-color: var(--line)">
                <label class="sr-only" for="brand-search">Search {{ many('workspace').toLowerCase() }}</label>
                <div class="flex items-center gap-2 rounded-lg px-2.5" style="background: var(--s2)">
                    <SIcon name="search" :size="15" />
                    <input
                        id="brand-search"
                        ref="searchInput"
                        v-model="search"
                        type="search"
                        class="w-full border-0 bg-transparent py-2 text-[13.5px] text-t1 outline-none placeholder:text-t4"
                        :placeholder="`Search ${many('workspace').toLowerCase()}`"
                        @keydown.esc="open = false"
                    />
                </div>
            </div>

            <div class="sc-scroll max-h-[320px] overflow-y-auto py-1">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-[13.5px] font-semibold text-t2 hover:bg-[color:var(--line)]"
                    @click="clear"
                >
                    <SIcon name="layers" :size="16" />
                    All {{ many('workspace').toLowerCase() }}
                </button>

                <p v-if="grouped.length === 0" class="px-3 py-6 text-center text-[13px] text-t3">
                    No {{ many('workspace').toLowerCase() }} match “{{ search }}”.
                </p>

                <div
                    v-for="brand in grouped"
                    :key="brand.id"
                    class="flex items-center gap-2 px-2 hover:bg-[color:var(--line)]"
                >
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-center gap-2.5 py-2.5 text-left"
                        role="option"
                        :aria-selected="selected.includes(brand.id)"
                        @click="select(brand)"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg text-[12px] font-extrabold"
                            :style="{ background: brand.color || 'var(--sc-accent)', color: '#fff' }"
                        >{{ String(brand.name || '?').charAt(0).toUpperCase() }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13.5px] font-bold text-t1">{{ brand.name }}</span>
                            <span class="flex items-center gap-2 text-[12px] text-t3">
                                <span>{{ brand.channels ?? 0 }} {{ many('channel').toLowerCase() }}</span>
                                <!-- Health is stated in words as well as colour:
                                     status must never be colour-only. -->
                                <span class="flex items-center gap-1">
                                    <span class="size-1.5 rounded-full" :style="{ background: healthTone(brand.health).color }"></span>
                                    {{ healthTone(brand.health).text }}
                                </span>
                                <span v-if="brand.pending">· {{ brand.pending }} awaiting review</span>
                            </span>
                        </span>

                        <SIcon v-if="selected.includes(brand.id)" name="check" :size="16" label="Selected" />
                    </button>

                    <button
                        type="button"
                        class="sc-btn sc-btn-ghost !p-2"
                        :aria-pressed="favorites.includes(brand.id)"
                        :aria-label="favorites.includes(brand.id) ? `Unfavourite ${brand.name}` : `Favourite ${brand.name}`"
                        @click.stop="toggleFavorite(brand)"
                    >
                        <SIcon
                            name="star"
                            :size="15"
                            :style="favorites.includes(brand.id) ? 'color: var(--warn)' : ''"
                        />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
