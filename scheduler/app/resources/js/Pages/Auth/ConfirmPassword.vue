<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import SButton from '@/Components/UI/SButton.vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

const submit = () => form.post(route('password.confirm'), {
    onFinish: () => {
        form.reset();
        passwordInput.value.focus();
    },
});
</script>

<template>
    <Head title="Secure area" />

    <AuthLayout
        title="Confirm it's you."
        subtitle="This part of the app is protected. Enter your password to continue."
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="sc-label" for="password">Password</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="sc-input"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <SButton type="submit" variant="primary" class="w-full !py-3" :disabled="form.processing">
                Confirm →
            </SButton>
        </form>
    </AuthLayout>
</template>
