<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    branding: Object,
    customDomain: String,
    domainVerified: Boolean,
    verifyToken: String,
    cnameTarget: String,
});

const form = useForm({
    name: props.branding.name ?? '',
    tagline: props.branding.tagline ?? '',
    primary: props.branding.primary ?? '#4f46e5',
    secondary: props.branding.secondary ?? '#0ea5e9',
    logo: props.branding.logo ?? '',
});

const domainForm = useForm({ custom_domain: props.customDomain ?? '' });

const save = () => form.put(route('branding.update'), { preserveScroll: true });
const saveDomain = () => domainForm.put(route('branding.domain'), { preserveScroll: true });
</script>

<template>
    <AppLayout title="White-label">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">White-label settings</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Branding -->
                <form @submit.prevent="save" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Dashboard branding</h3>
                    <div>
                        <InputLabel for="name" value="App name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="tagline" value="Tagline" />
                        <TextInput id="tagline" v-model="form.tagline" class="mt-1 block w-full" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="primary" value="Primary color" />
                            <input id="primary" type="color" v-model="form.primary" class="mt-1 h-10 w-full rounded-md border-gray-300" />
                        </div>
                        <div>
                            <InputLabel for="secondary" value="Secondary color" />
                            <input id="secondary" type="color" v-model="form.secondary" class="mt-1 h-10 w-full rounded-md border-gray-300" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="logo" value="Logo URL" />
                        <TextInput id="logo" v-model="form.logo" type="url" class="mt-1 block w-full" placeholder="https://…" />
                    </div>
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Save branding</PrimaryButton>
                    </div>
                </form>

                <!-- Custom domain -->
                <form @submit.prevent="saveDomain" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Custom domain</h3>
                    <p class="text-sm text-gray-500">
                        Point a CNAME record at <code class="bg-gray-100 px-1 rounded">{{ cnameTarget }}</code>,
                        then enter your domain below.
                    </p>
                    <div>
                        <InputLabel for="domain" value="Custom domain" />
                        <TextInput id="domain" v-model="domainForm.custom_domain" class="mt-1 block w-full" placeholder="app.youragency.com" />
                    </div>
                    <p v-if="customDomain" class="text-sm">
                        Status:
                        <span :class="domainVerified ? 'text-green-600' : 'text-amber-600'">
                            {{ domainVerified ? 'Verified' : 'Pending verification' }}
                        </span>
                        <span v-if="!domainVerified && verifyToken" class="block text-gray-500 mt-1">
                            Add a TXT record: <code class="bg-gray-100 px-1 rounded">{{ verifyToken }}</code>
                        </span>
                    </p>
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="domainForm.processing">Save domain</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
