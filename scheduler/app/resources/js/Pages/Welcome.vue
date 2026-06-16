<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

const branding = usePage().props.branding ?? {};
const appName = branding.name || 'Scheduleistic';

const features = [
    { title: '13 networks', body: 'LinkedIn, Facebook, Instagram, Threads, TikTok, YouTube, Pinterest, Mastodon, Bluesky, Medium, WordPress and more.' },
    { title: 'Schedule & queue', body: 'Plan posts, fill posting-time slots, and set recurring content. A reliable engine publishes on time with retries.' },
    { title: 'Agency-ready', body: 'Client workspaces, approval workflows, a client portal, and bulk CSV import.' },
    { title: 'AI captions', body: 'Generate on-brand copy per network in one click.' },
    { title: 'Analytics', body: 'Track engagement across every connected account.' },
    { title: 'White-label', body: 'Your brand, your colours, and your customers’ own custom domains with automatic HTTPS.' },
];
</script>

<template>
    <Head :title="`${appName} — Social media scheduling`" />

    <div class="min-h-screen bg-gradient-to-b from-indigo-50 via-white to-white text-gray-900">
        <!-- Nav -->
        <header class="max-w-6xl mx-auto px-6 py-6 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <img v-if="branding.logo" :src="branding.logo" alt="logo" class="h-8 w-8 rounded" />
                <span class="text-xl font-bold tracking-tight">{{ appName }}</span>
            </div>
            <nav v-if="canLogin" class="flex items-center gap-4 text-sm">
                <Link :href="route('login')" class="text-gray-600 hover:text-gray-900">Log in</Link>
                <Link v-if="canRegister" :href="route('register')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Get started
                </Link>
            </nav>
        </header>

        <!-- Hero -->
        <section class="max-w-3xl mx-auto px-6 pt-16 pb-20 text-center">
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight">
                {{ branding.tagline || 'Schedule once. Publish everywhere.' }}
            </h1>
            <p class="mt-5 text-lg text-gray-600">
                {{ appName }} is the all-in-one platform to plan, schedule, and publish content across
                every social network — built for agencies and teams.
            </p>
            <div v-if="canRegister" class="mt-8 flex items-center justify-center gap-3">
                <Link :href="route('register')"
                    class="px-6 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium">
                    Start free
                </Link>
                <Link :href="route('login')"
                    class="px-6 py-3 border border-gray-300 rounded-md hover:bg-gray-50 font-medium">
                    Log in
                </Link>
            </div>
        </section>

        <!-- Features -->
        <section class="max-w-6xl mx-auto px-6 pb-24">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="f in features" :key="f.title" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-semibold text-gray-900">{{ f.title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ f.body }}</p>
                </div>
            </div>
        </section>

        <footer class="border-t border-gray-100 py-8 text-center text-sm text-gray-400">
            {{ branding.powered_by || `© ${new Date().getFullYear()} ${appName}` }}
        </footer>
    </div>
</template>
