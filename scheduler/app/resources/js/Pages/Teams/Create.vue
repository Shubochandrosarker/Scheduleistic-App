<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import CreateTeamForm from '@/Pages/Teams/Partials/CreateTeamForm.vue';
</script>

<template>
    <AppLayout title="Create Team">
        <PageHeader
            eyebrow="Organization"
            title="New organization"
            subtitle="A separate tenant with its own clients, billing, and branding."
        />

        <div>
            <div class="max-w-5xl">
                <CreateTeamForm />
            </div>
        </div>
    </AppLayout>
</template>
