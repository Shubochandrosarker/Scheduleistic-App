<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import SButton from '@/Components/UI/SButton.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <AuthLayout
        title="Welcome back."
        subtitle="Sign in — your queue kept moving without you."
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

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label class="sc-label" for="password">Password</label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="mb-1.5 text-[12px] font-bold"
                        style="color: var(--sc-accent-text)"
                    >Forgot?</Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="sc-input"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <label class="flex cursor-pointer items-center gap-2.5">
                <Checkbox v-model:checked="form.remember" name="remember" />
                <span class="text-[13px] text-t2">Keep me signed in for 30 days</span>
            </label>

            <SButton
                type="submit"
                variant="primary"
                class="w-full !py-3"
                :disabled="form.processing"
            >{{ form.processing ? 'Signing in…' : 'Sign in →' }}</SButton>
        </form>

        <p class="mt-6 text-center text-[13px] text-t3">
            New here?
            <Link :href="route('register')" class="font-bold" style="color: var(--sc-accent-text)">
                Create an account
            </Link>
        </p>
    </AuthLayout>
</template>
