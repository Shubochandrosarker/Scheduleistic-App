<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import SButton from '@/Components/UI/SButton.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('password.update'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout title="Choose a new password." subtitle="Pick something long; you only type it once a month.">
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
                <label class="sc-label" for="password">New password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="sc-input"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <label class="sc-label" for="password_confirmation">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="sc-input"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <SButton type="submit" variant="primary" class="w-full !py-3" :disabled="form.processing">
                Reset password →
            </SButton>
        </form>
    </AuthLayout>
</template>
