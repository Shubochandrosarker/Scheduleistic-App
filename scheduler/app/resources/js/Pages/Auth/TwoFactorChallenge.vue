<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import SButton from '@/Components/UI/SButton.vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value = !recovery.value;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => form.post(route('two-factor.login'));
</script>

<template>
    <Head title="Two-factor confirmation" />

    <AuthLayout
        title="One more step."
        :subtitle="recovery
            ? 'Enter one of your emergency recovery codes.'
            : 'Enter the code from your authenticator app.'"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div v-if="!recovery">
                <label class="sc-label" for="code">Authentication code</label>
                <input
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="sc-input text-center !text-[22px] !font-extrabold tracking-[0.4em]"
                    placeholder="000000"
                    autofocus
                />
                <InputError class="mt-1.5" :message="form.errors.code" />
            </div>

            <div v-else>
                <label class="sc-label" for="recovery_code">Recovery code</label>
                <input
                    id="recovery_code"
                    ref="recoveryCodeInput"
                    v-model="form.recovery_code"
                    type="text"
                    autocomplete="one-time-code"
                    class="sc-input font-mono"
                />
                <InputError class="mt-1.5" :message="form.errors.recovery_code" />
            </div>

            <SButton type="submit" variant="primary" class="w-full !py-3" :disabled="form.processing">
                Verify &amp; sign in →
            </SButton>
        </form>

        <p class="mt-6 text-center">
            <button
                type="button"
                class="text-[13px] font-bold"
                style="color: var(--sc-accent-text)"
                @click.prevent="toggleRecovery"
            >{{ recovery ? 'Use an authentication code' : 'Use a recovery code' }}</button>
        </p>
    </AuthLayout>
</template>
