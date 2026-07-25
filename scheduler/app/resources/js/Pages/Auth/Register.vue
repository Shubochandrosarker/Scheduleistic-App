<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import SButton from '@/Components/UI/SButton.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthLayout
        title="Start your agency."
        subtitle="One account holds every client workspace, channel, and approval."
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="sc-label" for="name">Your name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="sc-input"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <label class="sc-label" for="email">Work email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="sc-input"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="sc-label" for="password">Password</label>
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
                    <label class="sc-label" for="password_confirmation">Confirm</label>
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
            </div>

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature">
                <label class="flex cursor-pointer items-start gap-2.5">
                    <Checkbox id="terms" v-model:checked="form.terms" name="terms" required class="mt-0.5" />
                    <span class="text-[12.5px] leading-relaxed text-t2">
                        I agree to the
                        <a
                            :href="route('terms.show')"
                            target="_blank"
                            class="font-bold"
                            style="color: var(--sc-accent-text)"
                        >Terms of Service</a>
                        and
                        <a
                            :href="route('policy.show')"
                            target="_blank"
                            class="font-bold"
                            style="color: var(--sc-accent-text)"
                        >Privacy Policy</a>.
                    </span>
                </label>
                <InputError class="mt-1.5" :message="form.errors.terms" />
            </div>

            <SButton
                type="submit"
                variant="primary"
                class="w-full !py-3"
                :disabled="form.processing"
            >{{ form.processing ? 'Creating…' : 'Create account →' }}</SButton>
        </form>

        <p class="mt-6 text-center text-[13px] text-t3">
            Already registered?
            <Link :href="route('login')" class="font-bold" style="color: var(--sc-accent-text)">Sign in</Link>
        </p>
    </AuthLayout>
</template>
