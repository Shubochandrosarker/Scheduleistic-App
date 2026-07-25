<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import { useBrand } from '@/composables/useBrand';

defineProps({
    terms: String,
});

useBrand();
</script>

<template>
    <Head title="Terms of Service" />

    <div class="min-h-screen px-5 py-12" style="background: var(--bg)">
        <div class="mx-auto max-w-2xl">
            <AuthenticationCardLogo />

            <article
                class="sc-card prose prose-invert mt-7 max-w-none p-7 prose-headings:text-t1 prose-p:text-t2 prose-li:text-t2 prose-strong:text-t1 prose-a:text-[color:var(--sc-accent-text)]"
                v-html="terms"
            />
        </div>
    </div>
</template>
