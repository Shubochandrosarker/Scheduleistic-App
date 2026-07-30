<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SIcon from '@/Components/UI/SIcon.vue';

/**
 * The standard filter component.
 *
 * Every list surface in 2.0 filters the same way, and every filter lives in
 * the URL. That is deliberate: a filtered view is then a link you can send to
 * a colleague, a bookmark that survives a reload, and a back button that does
 * what people expect. Nothing here holds hidden state.
 *
 * `facets` is a declarative description of the available filters:
 *   { key, label, type: 'multi'|'search'|'daterange', options: [{value,label,color}] }
 */
const props = defineProps({
    facets: { type: Array, required: true },
    /** Current values, keyed by facet key. Arrays for multi, strings otherwise. */
    modelValue: { type: Object, default: () => ({}) },
    /** Route name the filters are pushed to. */
    routeName: { type: String, required: true },
    /** Extra query params preserved on every navigation (e.g. the calendar view). */
    preserve: { type: Object, default: () => ({}) },
    savedViewsKey: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const openFacet = ref(null);
const searchTerm = ref(props.modelValue.search ?? '');

const values = computed(() => props.modelValue ?? {});

const activeCount = computed(() => props.facets.reduce((total, facet) => {
    const value = values.value[facet.key];

    if (Array.isArray(value)) return total + value.length;

    return total + (value ? 1 : 0);
}, 0));

/** Push the new filter set into the URL — the single source of truth. */
const apply = (next) => {
    emit('update:modelValue', next);

    router.get(route(props.routeName), { ...props.preserve, ...pruned(next) }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

/** Drop empty values so the URL stays readable. */
const pruned = (source) => Object.fromEntries(
    Object.entries(source).filter(([, value]) => Array.isArray(value) ? value.length > 0 : Boolean(value)),
);

const toggleOption = (facet, option) => {
    const current = Array.isArray(values.value[facet.key]) ? [...values.value[facet.key]] : [];
    const index = current.indexOf(option.value);

    index === -1 ? current.push(option.value) : current.splice(index, 1);

    apply({ ...values.value, [facet.key]: current });
};

const setValue = (key, value) => apply({ ...values.value, [key]: value });

const clearAll = () => {
    searchTerm.value = '';
    apply({});
};

const isSelected = (facet, option) => (values.value[facet.key] ?? []).includes(option.value);

// Debounce the caption search so typing does not fire a request per keystroke.
let searchTimer = null;
watch(searchTerm, (term) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => setValue('search', term.trim()), 350);
});

// --- Saved views -------------------------------------------------------
// Stored per surface in localStorage. A saved view is just a captured filter
// object, so restoring one is the same code path as clicking the filters.

const savedViews = ref(readViews());

function readViews() {
    if (!props.savedViewsKey) return [];

    try {
        return JSON.parse(window.localStorage.getItem(`scheduleistic.views.${props.savedViewsKey}`) ?? '[]');
    } catch {
        return [];
    }
}

function persistViews() {
    try {
        window.localStorage.setItem(`scheduleistic.views.${props.savedViewsKey}`, JSON.stringify(savedViews.value));
    } catch {
        // Storage unavailable; saved views are best-effort.
    }
}

const saveCurrentView = () => {
    const name = window.prompt('Name this view');
    if (!name) return;

    savedViews.value = [...savedViews.value.filter((v) => v.name !== name), { name, filters: pruned(values.value) }];
    persistViews();
};

const removeView = (name) => {
    savedViews.value = savedViews.value.filter((v) => v.name !== name);
    persistViews();
};

const labelFor = (facet) => {
    const value = values.value[facet.key];
    const count = Array.isArray(value) ? value.length : 0;

    return count > 0 ? `${facet.label} (${count})` : facet.label;
};
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-center gap-2">
            <!-- Search -->
            <div class="flex min-w-[200px] flex-1 items-center gap-2 rounded-xl border px-3"
                 style="background: var(--s3); border-color: var(--line2)">
                <SIcon name="search" :size="15" />
                <label class="sr-only" for="filter-search">Search</label>
                <input
                    id="filter-search"
                    v-model="searchTerm"
                    type="search"
                    class="w-full border-0 bg-transparent py-2.5 text-[14px] text-t1 outline-none placeholder:text-t4"
                    placeholder="Search captions and titles"
                />
            </div>

            <!-- Multi-select facets -->
            <div v-for="facet in facets" :key="facet.key" class="relative">
                <button
                    type="button"
                    class="sc-pill flex items-center gap-1.5"
                    :class="(values[facet.key] ?? []).length ? 'sc-pill-on' : ''"
                    :aria-expanded="openFacet === facet.key"
                    aria-haspopup="listbox"
                    @click="openFacet = openFacet === facet.key ? null : facet.key"
                >
                    {{ labelFor(facet) }}
                    <SIcon name="chevronDown" :size="13" />
                </button>

                <div v-if="openFacet === facet.key" class="fixed inset-0 z-40" @click="openFacet = null"></div>

                <div
                    v-if="openFacet === facet.key"
                    class="sc-scroll absolute left-0 z-50 mt-2 max-h-[300px] w-[240px] overflow-y-auto rounded-xl border p-1 shadow-lg"
                    style="background: var(--pop-bg); border-color: var(--line2)"
                    role="listbox"
                    :aria-label="facet.label"
                >
                    <p v-if="!facet.options.length" class="px-3 py-4 text-center text-[13px] text-t3">
                        Nothing to filter by yet.
                    </p>

                    <button
                        v-for="option in facet.options"
                        :key="option.value"
                        type="button"
                        role="option"
                        :aria-selected="isSelected(facet, option)"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13.5px] font-semibold text-t2 hover:bg-[color:var(--line)]"
                        @click="toggleOption(facet, option)"
                    >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border"
                            :style="isSelected(facet, option)
                                ? 'background: var(--sc-accent); border-color: var(--sc-accent)'
                                : 'border-color: var(--line2)'"
                        >
                            <SIcon v-if="isSelected(facet, option)" name="check" :size="11" :stroke-width="3" style="color: #fff" />
                        </span>
                        <span v-if="option.color" class="size-2 shrink-0 rounded-full" :style="{ background: option.color }"></span>
                        <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                    </button>
                </div>
            </div>

            <button v-if="activeCount" type="button" class="sc-btn sc-btn-sm sc-btn-ghost" @click="clearAll">
                <SIcon name="close" :size="13" />
                Clear all ({{ activeCount }})
            </button>

            <button v-if="savedViewsKey" type="button" class="sc-btn sc-btn-sm sc-btn-secondary" @click="saveCurrentView">
                <SIcon name="star" :size="13" />
                Save view
            </button>
        </div>

        <div v-if="savedViews.length" class="flex flex-wrap items-center gap-2">
            <span class="text-[12px] font-bold text-t4">Saved views</span>
            <span
                v-for="view in savedViews"
                :key="view.name"
                class="flex items-center gap-1 rounded-full border px-1 pl-3"
                style="border-color: var(--line2); background: var(--s2)"
            >
                <button type="button" class="py-1.5 text-[12.5px] font-semibold text-t2" @click="apply(view.filters)">
                    {{ view.name }}
                </button>
                <button
                    type="button"
                    class="rounded-full p-1.5 text-t4 hover:text-t1"
                    :aria-label="`Delete saved view ${view.name}`"
                    @click="removeView(view.name)"
                >
                    <SIcon name="close" :size="11" />
                </button>
            </span>
        </div>
    </div>
</template>
