<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import SidebarNav from '@/Layouts/Partials/SidebarNav.vue';
import TopBar from '@/Layouts/Partials/TopBar.vue';
import { useBrand } from '@/composables/useBrand';

defineProps({
    title: String,
});

// Repaint the accent tokens from the tenant's white-label branding.
useBrand();

const showingNavigationDropdown = ref(false);

// Friendly text for the short status codes controllers set via
// back()->with('status', '...'). Anything not listed here still shows —
// just humanized (dashes → spaces) — so a new status code never goes silent.
const STATUS_MESSAGES = {
    'workspace-created': 'Workspace created.',
    'workspace-deleted': 'Workspace deleted.',
    'channel-connected': 'Channel connected.',
    'channel-disconnected': 'Channel disconnected.',
    'post-scheduled': 'Post scheduled.',
    'slot-added': 'Time slot added.',
    'slot-removed': 'Time slot removed.',
    'feed-added': 'Feed added.',
    'feed-removed': 'Feed removed.',
    'comment-added': 'Comment added.',
    'submitted-for-approval': 'Submitted for approval.',
    'approval-decided': 'Decision recorded.',
};

const page = usePage();
const dismissed = ref(false);
watch(() => page.props.status, () => { dismissed.value = false; });

const statusMessage = computed(() => {
    const status = page.props.status;
    if (!status || dismissed.value) return null;

    if (STATUS_MESSAGES[status]) return STATUS_MESSAGES[status];

    // "imported-3-skipped-1" → "Imported 3, skipped 1."
    const imported = status.match(/^imported-(\d+)-skipped-(\d+)$/);
    if (imported) return `Imported ${imported[1]} post(s), skipped ${imported[2]}.`;

    return status.replace(/-/g, ' ').replace(/^./, (c) => c.toUpperCase()) + '.';
});

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="flex min-h-screen items-start" style="background: var(--bg)">
            <!-- Rail: fixed on desktop, a slide-over on small screens. -->
            <SidebarNav class="hidden lg:flex" @logout="logout" />

            <div
                v-if="showingNavigationDropdown"
                class="fixed inset-0 z-40 bg-black/60 lg:hidden"
                @click="showingNavigationDropdown = false"
            ></div>
            <SidebarNav
                v-if="showingNavigationDropdown"
                class="fixed inset-y-0 left-0 z-50 flex lg:hidden"
                @navigate="showingNavigationDropdown = false"
                @logout="logout"
            />

            <div class="flex min-w-0 flex-1 flex-col">
                <TopBar
                    :switch-to-team="switchToTeam"
                    @toggle-nav="showingNavigationDropdown = !showingNavigationDropdown"
                    @logout="logout"
                />

                <!-- Impersonation is a loaded gun; it gets its own permanent banner. -->
                <div
                    v-if="$page.props.isImpersonating"
                    class="flex flex-wrap items-center justify-between gap-3 border-b px-6 py-2.5 text-[12.5px] font-semibold"
                    style="background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.3); color: var(--warn)"
                >
                    <span>You are impersonating this organization. Everything you do is audit-logged.</span>
                    <Link
                        :href="route('admin.stop-impersonating')"
                        method="post"
                        as="button"
                        class="sc-btn sc-btn-sm sc-btn-warn"
                    >
                        Stop impersonating
                    </Link>
                </div>

                <!-- Flash status from back()->with('status', ...). -->
                <div
                    v-if="statusMessage"
                    class="flex items-center justify-between gap-3 border-b px-6 py-2.5 text-[12.5px] font-semibold"
                    style="background: var(--sc-soft); border-color: var(--sc-mid); color: var(--sc-accent-text)"
                >
                    <span>{{ statusMessage }}</span>
                    <button
                        type="button"
                        class="text-t3 transition-colors hover:text-t1"
                        aria-label="Dismiss"
                        @click="dismissed = true"
                    >✕</button>
                </div>

                <main class="sc-scroll min-w-0 flex-1 px-5 py-7 sm:px-7 lg:px-8">
                    <!-- Pages that still declare a #header slot render it above their body. -->
                    <div v-if="$slots.header" class="mb-6">
                        <slot name="header" />
                    </div>

                    <div class="animate-in">
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
