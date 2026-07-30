<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import BrandMark from '@/Layouts/Partials/BrandMark.vue';
import { useBrand } from '@/composables/useBrand';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

useBrand();

const page = usePage();
const branding = computed(() => page.props.branding ?? {});
const appName = computed(() => branding.value.name || 'Scheduleistic');

const stats = [
    { value: '13', label: 'networks' },
    { value: 'Retry', label: 'on every target' },
    { value: 'Queue', label: 'backed publishing' },
];

const features = [
    {
        title: '13 networks',
        body: 'LinkedIn, Facebook, Instagram, Threads, TikTok, YouTube, Pinterest, Mastodon, Bluesky, Medium, WordPress and more.',
    },
    {
        title: 'A real publishing engine',
        body: 'A cron dispatcher claims due posts; a queue worker publishes each target independently, with retries and per-channel rate limiting.',
    },
    {
        title: 'Agency workflow',
        body: 'Client workspaces, draft → approve → publish, threaded comments with @mentions, and a client portal scoped to one workspace.',
    },
    {
        title: 'AI that knows your brand',
        body: 'Caption, rewrite, hashtag, and quality-check agents, grounded in each client’s own brand voice.',
    },
    {
        title: 'Analytics on a schedule',
        body: 'Engagement captured by a job, not scraped at page load — so your reports and your dashboard agree.',
    },
    {
        title: 'White-label',
        body: 'Your brand, your colours, and your customers’ own custom domains with automatic HTTPS.',
    },
];
</script>

<template>
    <Head :title="`${appName} — Social media scheduling`" />

    <div class="min-h-screen" style="background: var(--bg)">
        <header class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-6 sm:px-6">
            <div class="flex min-w-0 items-center gap-2.5">
                <BrandMark :size="34" />
                <span class="truncate text-[18px] font-extrabold tracking-[-0.02em] text-t1">{{ appName }}</span>
            </div>

            <nav v-if="canLogin" class="flex shrink-0 items-center gap-2 sm:gap-3">
                <Link :href="route('login')" class="sc-btn sc-btn-sm sc-btn-ghost">Log in</Link>
                <Link v-if="canRegister" :href="route('register')" class="sc-btn sc-btn-sm sc-btn-primary">
                    Get started →
                </Link>
            </nav>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden px-6 pb-24 pt-16">
            <div
                class="pointer-events-none absolute left-1/2 top-0 size-[560px] -translate-x-1/2 rounded-full blur-3xl"
                style="background: var(--sc-soft); opacity: var(--glow-op)"
            ></div>

            <div class="relative mx-auto max-w-3xl text-center">
                <p class="sc-kicker mb-5">
                    13 networks · one workspace
                </p>

                <h1 class="text-[40px] font-extrabold leading-[1.06] tracking-[-0.035em] text-t1 sm:text-[58px]">
                    Plan it. Approve it.<br class="hidden sm:block" />
                    <span style="color: var(--sc-accent-text)">Ship it.</span>
                </h1>

                <p class="mx-auto mt-6 max-w-xl text-[16px] leading-relaxed text-t2">
                    {{ branding.tagline || `${appName} plans, approves, and publishes content across every
                    social network — built for agencies running many clients at once.` }}
                </p>

                <div v-if="canRegister" class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <Link :href="route('register')" class="sc-btn sc-btn-primary !px-6 !py-3">Start free →</Link>
                    <Link :href="route('login')" class="sc-btn sc-btn-secondary !px-6 !py-3">Log in</Link>
                </div>

                <dl class="mt-14 flex flex-wrap items-start justify-center gap-x-14 gap-y-6">
                    <div v-for="s in stats" :key="s.label">
                        <dt class="text-[28px] font-extrabold leading-none text-t1">{{ s.value }}</dt>
                        <dd class="mt-1.5 text-[12px] text-t3">{{ s.label }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Features -->
        <section class="mx-auto max-w-6xl px-6 pb-24">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="f in features" :key="f.title" class="sc-card p-6">
                    <h3 class="text-[15px] font-bold text-t1">{{ f.title }}</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-t3">{{ f.body }}</p>
                </article>
            </div>
        </section>

        <footer class="border-t py-8 text-center text-[12.5px] text-t4" style="border-color: var(--line)">
            {{ branding.powered_by || `© ${new Date().getFullYear()} ${appName}` }}
        </footer>
    </div>
</template>
