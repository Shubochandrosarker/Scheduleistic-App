<script setup>
import { computed, ref, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(true);
const style = ref('success');
const message = ref('');

watchEffect(async () => {
    style.value = page.props.jetstream.flash?.bannerStyle || 'success';
    message.value = page.props.jetstream.flash?.banner || '';
    show.value = true;
});

const danger = computed(() => style.value === 'danger');

const tone = computed(() => danger.value
    ? { fg: 'var(--bad)', bg: 'rgba(248,113,113,0.12)', bd: 'rgba(248,113,113,0.3)' }
    : { fg: 'var(--ok)', bg: 'rgba(52,211,153,0.12)', bd: 'rgba(52,211,153,0.3)' });
</script>

<template>
    <div>
        <div
            v-if="show && message"
            class="border-b px-4 py-2.5 sm:px-6"
            :style="{ background: tone.bg, borderColor: tone.bd }"
        >
            <div class="mx-auto flex max-w-screen-xl flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 flex-1 items-center gap-2.5">
                    <svg
                        v-if="!danger"
                        class="size-5 shrink-0"
                        :style="{ color: tone.fg }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.8"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>

                    <svg
                        v-else
                        class="size-5 shrink-0"
                        :style="{ color: tone.fg }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.8"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>

                    <p class="truncate text-[12.5px] font-bold" :style="{ color: tone.fg }">
                        {{ message }}
                    </p>
                </div>

                <button
                    type="button"
                    class="shrink-0 rounded-md p-1 transition-opacity hover:opacity-60 focus:outline-none"
                    :style="{ color: tone.fg }"
                    aria-label="Dismiss"
                    @click.prevent="show = false"
                >
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
