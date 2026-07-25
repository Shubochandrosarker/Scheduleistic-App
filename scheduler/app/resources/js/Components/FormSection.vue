<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <SectionTitle>
            <template #title>
                <slot name="title" />
            </template>
            <template #description>
                <slot name="description" />
            </template>
        </SectionTitle>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <form @submit.prevent="$emit('submitted')">
                <div
                    class="sc-card px-4 py-5 sm:p-6"
                    :class="hasActions ? '!rounded-b-none' : ''"
                >
                    <div class="grid grid-cols-6 gap-6">
                        <slot name="form" />
                    </div>
                </div>

                <div
                    v-if="hasActions"
                    class="flex items-center justify-end gap-2.5 rounded-b-2xl border border-t-0 px-4 py-3 text-end sm:px-6"
                    style="background: var(--s2); border-color: var(--card-line)"
                >
                    <slot name="actions" />
                </div>
            </form>
        </div>
    </div>
</template>
