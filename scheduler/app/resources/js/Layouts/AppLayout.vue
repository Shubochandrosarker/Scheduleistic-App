<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import SidebarNav from '@/Layouts/Partials/SidebarNav.vue';
import TopBar from '@/Layouts/Partials/TopBar.vue';
import MobileNav from '@/Layouts/Partials/MobileNav.vue';
import SIcon from '@/Components/UI/SIcon.vue';
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
    'campaign-created': 'Campaign created.',
    'campaign-updated': 'Campaign updated.',
    'campaign-deleted': 'Campaign deleted.',
    'pillar-created': 'Content pillar created.',
    'pillar-deleted': 'Content pillar removed.',
    'tag-created': 'Tag added.',
    'tag-deleted': 'Tag removed.',
    'idea-created': 'Idea captured.',
    'idea-updated': 'Idea updated.',
    'idea-deleted': 'Idea deleted.',
    'asset-uploaded': 'Upload received — processing in the background.',
    'asset-updated': 'Asset updated.',
    'asset-deleted': 'Asset deleted.',
    'folder-created': 'Folder created.',
    'folder-deleted': 'Folder removed.',
    'health-refreshed': 'Connection health re-checked.',
    'preferences-saved': 'Notification preferences saved.',
    'notifications-cleared': 'All notifications marked as read.',
    'webhook-created': 'Webhook endpoint created.',
    'webhook-updated': 'Webhook endpoint updated.',
    'webhook-deleted': 'Webhook endpoint deleted.',
    'webhook-secret-rotated': 'Signing secret rotated.',
    'webhook-replayed': 'Delivery queued for replay.',
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

    // "bulk-reschedule-12" -> "Rescheduled 12 posts."
    const bulk = status.match(/^bulk-([a-z]+)-(\d+)$/);
    if (bulk) {
        const verbs = {
            reschedule: 'Rescheduled', delete: 'Deleted', submit: 'Submitted for approval',
            approve: 'Reviewed', assign: 'Reassigned', tag: 'Tagged', campaign: 'Moved',
        };
        return `${verbs[bulk[1]] ?? 'Updated'} ${bulk[2]} post(s).`;
    }

    const converted = status.match(/^idea-converted-(\d+)$/);
    if (converted) return `Created ${converted[1]} draft(s) from the idea.`;

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

        <!-- First tab stop on every page: jump past the rail to the content. -->
        <a href="#main-content" class="sc-skip-link">Skip to main content</a>

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
                force-expanded
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
                    >
                        <SIcon name="close" :size="14" label="Dismiss" />
                    </button>
                </div>

                <!-- pb-24 on small screens clears the fixed bottom navigation. -->
                <main id="main-content" class="sc-scroll min-w-0 flex-1 px-5 pb-24 pt-7 sm:px-7 lg:px-8 lg:pb-8">
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

        <MobileNav @more="showingNavigationDropdown = true" />
    </div>
</template>
