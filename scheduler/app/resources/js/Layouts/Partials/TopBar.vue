<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps({
    switchToTeam: Function,
});

const emit = defineEmits(['toggle-nav', 'logout']);

const page = usePage();
const { theme, toggleTheme } = useTheme();

const user = computed(() => page.props.auth?.user ?? {});
const currentTeam = computed(() => user.value.current_team ?? null);
const allTeams = computed(() => user.value.all_teams ?? []);
const jetstream = computed(() => page.props.jetstream ?? {});

const initials = computed(() => {
    const name = String(user.value.name ?? '').trim();
    if (!name) return '·';

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const teamInitials = computed(() => {
    const name = String(currentTeam.value?.name ?? '').trim();
    if (!name) return '·';

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex min-w-0 items-center gap-2 border-b px-4 py-3 backdrop-blur-xl sm:gap-3 sm:px-6"
        style="background: var(--head-bg); border-color: var(--line)"
    >
        <button
            type="button"
            class="sc-btn sc-btn-sm sc-btn-secondary lg:hidden"
            aria-label="Toggle navigation"
            @click="emit('toggle-nav')"
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Organization switcher — the tenant boundary, so it leads the bar.
             Below `sm` it collapses to the initials chip to keep the bar on one row. -->
        <Dropdown v-if="jetstream.hasTeamFeatures && currentTeam" align="left" width="60" class="min-w-0">
            <template #trigger>
                <button
                    type="button"
                    class="flex max-w-[240px] items-center gap-2.5 rounded-xl border px-2 py-1.5 transition-colors sm:px-2.5"
                    style="background: var(--s2); border-color: var(--line2)"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg text-[11px] font-extrabold"
                        style="background: linear-gradient(135deg, var(--sc-accent), var(--sc-accent-2)); color: var(--on-acc)"
                    >{{ teamInitials }}</span>
                    <span class="hidden min-w-0 text-left sm:block">
                        <span class="block truncate text-[13px] font-bold leading-tight text-t1">{{ currentTeam.name }}</span>
                        <span class="block text-[10.5px] leading-tight text-t4">Organization</span>
                    </span>
                    <span class="hidden text-[10px] text-t4 sm:inline">▾</span>
                </button>
            </template>

            <template #content>
                <div class="w-60">
                    <div class="block px-4 py-2 text-xs text-t4">Manage organization</div>

                    <DropdownLink :href="route('teams.show', currentTeam)">Settings</DropdownLink>
                    <DropdownLink v-if="jetstream.canCreateTeams" :href="route('teams.create')">
                        New organization
                    </DropdownLink>

                    <template v-if="allTeams.length > 1">
                        <div class="border-t" style="border-color: var(--line)" />
                        <div class="block px-4 py-2 text-xs text-t4">Switch</div>

                        <form v-for="team in allTeams" :key="team.id" @submit.prevent="props.switchToTeam(team)">
                            <DropdownLink as="button">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-1.5 rounded-full"
                                        :style="{ background: team.id === user.current_team_id ? 'var(--ok)' : 'var(--line2)' }"
                                    ></span>
                                    {{ team.name }}
                                </span>
                            </DropdownLink>
                        </form>
                    </template>
                </div>
            </template>
        </Dropdown>

        <div class="flex-1"></div>

        <button
            type="button"
            class="sc-btn sc-btn-sm sc-btn-secondary shrink-0"
            :title="`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`"
            @click="toggleTheme"
        >
            <span>{{ theme === 'dark' ? '☼' : '☾' }}</span>
            <span class="hidden sm:inline">{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
        </button>

        <Link :href="route('posts.create')" class="sc-btn sc-btn-primary sc-btn-sm shrink-0 sm:!px-4 sm:!py-2.5 sm:!text-[13px]">
            <span aria-hidden="true">+</span>
            <span class="hidden sm:inline">New post</span>
        </Link>

        <Dropdown align="right" width="48" class="shrink-0">
            <template #trigger>
                <button
                    v-if="jetstream.managesProfilePhotos && user.profile_photo_url"
                    type="button"
                    class="rounded-full border-2 border-transparent transition focus:outline-none"
                >
                    <img class="size-9 rounded-full object-cover" :src="user.profile_photo_url" :alt="user.name" />
                </button>
                <button
                    v-else
                    type="button"
                    class="flex size-9 items-center justify-center rounded-full text-[12px] font-extrabold"
                    style="background: linear-gradient(135deg, var(--alt), var(--sc-accent)); color: #fff"
                >{{ initials }}</button>
            </template>

            <template #content>
                <div class="block px-4 py-2 text-xs text-t4">
                    {{ user.name }}
                </div>

                <DropdownLink :href="route('profile.show')">Profile</DropdownLink>
                <DropdownLink v-if="jetstream.hasApiFeatures" :href="route('api-tokens.index')">API tokens</DropdownLink>

                <div class="border-t" style="border-color: var(--line)" />

                <form @submit.prevent="emit('logout')">
                    <DropdownLink as="button">Sign out</DropdownLink>
                </form>
            </template>
        </Dropdown>
    </header>
</template>
