<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import SButton from '@/Components/UI/SButton.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head title="Forgot password" />

    <AuthLayout
        title="Reset your password."
        subtitle="Tell us your email and we'll send a link to choose a new one."
    >
        <div
            v-if="status"
            class="mb-5 rounded-xl border px-4 py-3 text-[12.5px] font-semibold"
            style="background: rgba(52,211,153,0.1); border-color: rgba(52,211,153,0.3); color: var(--ok)"
        >{{ status }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="sc-label" for="email">Work email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="sc-input"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <SButton type="submit" variant="primary" class="w-full !py-3" :disabled="form.processing">
                {{ form.processing ? 'Sending…' : 'Email a reset link →' }}
            </SButton>
        </form>

        <p class="mt-6 text-center text-[13px] text-t3">
            Remembered it?
            <Link :href="route('login')" class="font-bold" style="color: var(--sc-accent-text)">Back to sign in</Link>
        </p>
    </AuthLayout>
</template>
