<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SButton from '@/Components/UI/SButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => form.post(route('verification.send'));

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Email verification" />

    <AuthLayout
        title="Verify your email."
        subtitle="Click the link we just emailed you and your workspace is ready. No email? Send another."
    >
        <div
            v-if="verificationLinkSent"
            class="mb-5 rounded-xl border px-4 py-3 text-[12.5px] font-semibold"
            style="background: rgba(52,211,153,0.1); border-color: rgba(52,211,153,0.3); color: var(--ok)"
        >A new verification link has been sent to your email address.</div>

        <form @submit.prevent="submit">
            <SButton type="submit" variant="primary" class="w-full !py-3" :disabled="form.processing">
                {{ form.processing ? 'Sending…' : 'Resend verification email' }}
            </SButton>
        </form>

        <div class="mt-6 flex items-center justify-center gap-5 text-[13px]">
            <Link :href="route('profile.show')" class="font-bold" style="color: var(--sc-accent-text)">Edit profile</Link>
            <Link :href="route('logout')" method="post" as="button" class="font-semibold text-t3">Sign out</Link>
        </div>
    </AuthLayout>
</template>
