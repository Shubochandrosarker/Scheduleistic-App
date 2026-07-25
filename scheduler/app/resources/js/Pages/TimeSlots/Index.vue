<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    workspace: Object,
    timeSlots: Array,
});

// Monday-first for display; the stored day_of_week stays 0=Sunday.
const DAYS = [
    { index: 1, label: 'Mon' },
    { index: 2, label: 'Tue' },
    { index: 3, label: 'Wed' },
    { index: 4, label: 'Thu' },
    { index: 5, label: 'Fri' },
    { index: 6, label: 'Sat' },
    { index: 0, label: 'Sun' },
];

const form = useForm({
    day_of_week: 1,
    time: '09:00',
});

// The "+ Slot" button under a column pre-selects that day, so adding a slot
// never means re-picking the day you just clicked.
const addingFor = ref(null);

const openAdd = (dayIndex) => {
    addingFor.value = addingFor.value === dayIndex ? null : dayIndex;
    form.day_of_week = dayIndex;
};

const create = () => form.post(route('workspaces.time-slots.store', props.workspace.id), {
    preserveScroll: true,
    onSuccess: () => { addingFor.value = null; },
});

const destroy = (id) => {
    useForm({}).delete(route('workspaces.time-slots.destroy', [props.workspace.id, id]), { preserveScroll: true });
};

const byDay = computed(() => {
    const groups = {};
    for (const day of DAYS) groups[day.index] = [];

    for (const slot of props.timeSlots) {
        if (groups[slot.day_of_week]) groups[slot.day_of_week].push(slot);
    }

    for (const key of Object.keys(groups)) {
        groups[key].sort((a, b) => String(a.time).localeCompare(String(b.time)));
    }

    return groups;
});

const total = computed(() => props.timeSlots.length);
</script>

<template>
    <AppLayout :title="`Queue slots — ${workspace.name}`">
        <PageHeader eyebrow="Posting rhythm">
            <template #title>Queue &amp; posting slots</template>
            <template #subtitle>
                "Add to queue" always lands in the next open slot below. Set the rhythm once per
                workspace and stop picking times by hand.
            </template>
            <template #actions>
                <SButton :href="route('workspaces.index')">← All clients</SButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-5 py-3.5"
             style="background: var(--s2); border-color: var(--line)">
            <p class="text-[13px] font-bold text-t1">{{ workspace.name }}</p>
            <p class="text-[12px] text-t3">
                {{ total }} slot{{ total === 1 ? '' : 's' }} · timezone {{ workspace.timezone }}
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <div v-for="day in DAYS" :key="day.index" class="sc-card flex flex-col p-3.5">
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <span class="text-[12px] font-extrabold uppercase tracking-[0.1em] text-t1">{{ day.label }}</span>
                    <span class="text-[11px] text-t4">
                        {{ byDay[day.index].length }} slot{{ byDay[day.index].length === 1 ? '' : 's' }}
                    </span>
                </div>

                <ul class="flex-1 space-y-1.5">
                    <li
                        v-for="slot in byDay[day.index]"
                        :key="slot.id"
                        class="group flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2"
                        style="background: var(--sc-soft); border-color: var(--sc-mid)"
                    >
                        <span class="text-[12.5px] font-bold" style="color: var(--sc-accent-text)">{{ slot.time }}</span>
                        <button
                            type="button"
                            class="text-[11px] font-bold opacity-0 transition-opacity group-hover:opacity-100"
                            style="color: var(--bad)"
                            aria-label="Remove slot"
                            @click="destroy(slot.id)"
                        >✕</button>
                    </li>

                    <li v-if="byDay[day.index].length === 0" class="py-2 text-center text-[11.5px] text-t4">
                        No slots
                    </li>
                </ul>

                <button
                    type="button"
                    class="mt-2 rounded-lg border border-dashed py-2 text-[12px] font-bold text-t3 transition-colors hover:text-t1"
                    style="border-color: var(--line2)"
                    @click="openAdd(day.index)"
                >{{ addingFor === day.index ? 'Cancel' : '+ Slot' }}</button>

                <form
                    v-if="addingFor === day.index"
                    class="mt-2 space-y-2"
                    @submit.prevent="create"
                >
                    <input v-model="form.time" type="time" class="sc-input !px-2.5 !py-2 !text-[12.5px]" required />
                    <SButton type="submit" size="sm" variant="primary" class="w-full" :disabled="form.processing">
                        Add
                    </SButton>
                    <InputError :message="form.errors.time" />
                    <InputError :message="form.errors.day_of_week" />
                </form>
            </div>
        </div>

        <SCard v-if="total === 0" class="mt-4">
            <p class="text-[13px] leading-relaxed text-t3">
                With no slots defined, "Add to queue" falls back to one hour from now. Add a few
                above to give this client a real posting cadence.
            </p>
        </SCard>
    </AppLayout>
</template>
