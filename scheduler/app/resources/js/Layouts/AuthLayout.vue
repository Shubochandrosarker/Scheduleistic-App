<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandMark from '@/Layouts/Partials/BrandMark.vue';
import { useBrand } from '@/composables/useBrand';

defineProps({
    title: String,
    subtitle: String,
});

useBrand();

const page = usePage();
const branding = computed(() => page.props.branding ?? {});

// Proof points for the marketing half. Deliberately about the engine, since
// that is what distinguishes this from a link-in-bio scheduler.
const stats = [
    { value: '13', label: 'networks' },
    { value: 'Retry', label: 'on every target' },
    { value: 'Queue', label: 'backed publishing' },
];
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]" style="background: var(--bg)">
        <!-- Marketing half — hidden on small screens so the form owns the fold. -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden p-12 lg:flex"
            style="background: var(--panel-bg)"
        >
            <div
                class="pointer-events-none absolute -right-24 -top-24 size-[420px] rounded-full blur-3xl"
                style="background: var(--sc-soft); opacity: var(--glow-op)"
            ></div>

            <Link href="/" class="relative flex items-center gap-3">
                <BrandMark :size="40" />
                <span>
                    <span class="block text-[17px] font-extrabold tracking-[-0.02em] text-t1">
                        {{ branding.name || 'Scheduleistic' }}
                    </span>
                    <span class="block font-mono text-[11px] text-t4">
                        {{ branding.tagline || 'Plan it. Approve it. Ship it.' }}
                    </span>
                </span>
            </Link>

            <div class="relative max-w-xl">
                <p class="sc-kicker mb-4">
                    13 networks · one workspace
                </p>
                <h2 class="text-[40px] font-extrabold leading-[1.06] tracking-[-0.035em] text-t1 xl:text-[52px]">
                    <span class="whitespace-nowrap">Plan it. Approve it.</span><br />
                    <span style="color: var(--sc-accent-text)">Ship it.</span>
                </h2>
                <p class="mt-5 text-[15px] leading-relaxed text-t2">
                    Every client, every channel, every approval in one queue — with a publishing
                    engine that retries instead of shrugging.
                </p>

                <dl class="mt-10 flex gap-12">
                    <div v-for="s in stats" :key="s.label">
                        <dt class="text-[26px] font-extrabold leading-none text-t1">{{ s.value }}</dt>
                        <dd class="mt-1.5 text-[12px] text-t3">{{ s.label }}</dd>
                    </div>
                </dl>
            </div>

            <p class="relative flex items-center gap-2 text-[12px] text-t3">
                <span class="size-1.5 rounded-full" style="background: var(--ok)"></span>
                Queue workers healthy · publishing on schedule
            </p>
        </div>

        <!-- Form half -->
        <div class="flex items-center justify-center px-5 py-12 sm:px-10">
            <div class="w-full max-w-[420px]">
                <Link href="/" class="mb-8 flex items-center gap-2.5 lg:hidden">
                    <BrandMark :size="34" />
                    <span class="text-[16px] font-extrabold tracking-[-0.02em] text-t1">
                        {{ branding.name || 'Scheduleistic' }}
                    </span>
                </Link>

                <h1 class="text-[30px] font-extrabold tracking-[-0.03em] text-t1">
                    <slot name="title">{{ title }}</slot>
                </h1>
                <p v-if="subtitle || $slots.subtitle" class="mt-2 text-[14px] leading-relaxed text-t3">
                    <slot name="subtitle">{{ subtitle }}</slot>
                </p>

                <div class="mt-7">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
