<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    workspace: Object,
    timeSlots: Array,
});

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const form = useForm({
    day_of_week: 1,
    time: '09:00',
});

const create = () => form.post(route('workspaces.time-slots.store', props.workspace.id), {
    preserveScroll: true,
});

const destroy = (id) => {
    useForm({}).delete(route('workspaces.time-slots.destroy', [props.workspace.id, id]), { preserveScroll: true });
};

const byDay = computed(() => {
    const groups = DAYS.map(() => []);
    for (const slot of props.timeSlots) {
        groups[slot.day_of_week].push(slot);
    }
    return groups;
});
</script>

<template>
    <AppLayout :title="`Time slots — ${workspace.name}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Queue time slots — {{ workspace.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Add a posting-time slot</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        When composing a post, checking "Add to queue" fills the next open slot
                        below instead of an exact time you pick yourself.
                    </p>
                    <form @submit.prevent="create" class="flex flex-wrap items-end gap-4">
                        <div>
                            <InputLabel for="day" value="Day" />
                            <select id="day" v-model.number="form.day_of_week"
                                class="mt-1 block border-gray-300 rounded-md shadow-sm">
                                <option v-for="(day, i) in DAYS" :key="i" :value="i">{{ day }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="time" value="Time" />
                            <input id="time" type="time" v-model="form.time"
                                class="mt-1 block border-gray-300 rounded-md shadow-sm" required />
                        </div>
                        <PrimaryButton :disabled="form.processing">Add slot</PrimaryButton>
                    </form>
                </div>

                <div class="bg-white shadow sm:rounded-lg divide-y">
                    <div v-if="timeSlots.length === 0" class="p-6 text-gray-500">
                        No time slots yet — "Add to queue" will fall back to one hour from now.
                    </div>
                    <div v-for="(slots, day) in byDay" :key="day">
                        <div v-if="slots.length" class="px-6 py-3 bg-gray-50">
                            <p class="text-xs uppercase tracking-wide text-gray-400 font-medium">{{ DAYS[day] }}</p>
                        </div>
                        <div v-for="slot in slots" :key="slot.id" class="px-6 py-3 flex items-center justify-between">
                            <span class="text-gray-900">{{ slot.time }}</span>
                            <DangerButton @click="destroy(slot.id)">Remove</DangerButton>
                        </div>
                    </div>
                </div>

                <Link :href="route('workspaces.index')" class="text-indigo-600 hover:underline text-sm">
                    ← Back to workspaces
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
