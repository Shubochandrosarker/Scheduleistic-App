<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useTerms } from '@/composables/useCapabilities';

const props = defineProps({
    switchToTeam: Function,
});

const emit = defineEmits(['toggle-nav', 'logout']);

const page = usePage();
const { theme, toggleTheme } = useTheme();
const { one } = useTerms();

const user = computed(() => page.props.auth?.user ?? {});
const currentTeam = computed(() => user.value.current_team ?? null);
const allTeams = computed(() => user.value.all_teams ?? []);
const jetstream = computed(() => page.props.jetstream ?? {});
const unread = computed(() => Number(page.props.unreadNotifications ?? 0));

const initialsOf = (value) => {
    const name = String(value ?? '').trim();
    if (!name) return '·';

    return name.split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
};

const initials = computed(() => initialsOf(user.value.name));
const teamInitials = computed(() => initialsOf(currentTeam.value?.name));
</script>

<template>
    <header
        class="sticky top-0 z-30 flex min-w-0 items-center gap-2 border-b px-4 py-3 backdrop-blur-xl sm:gap-3 sm:px-6"
        style="background: var(--head-bg); border-color: var(--line)"
    >
        <button
            type="button"
            class="sc-btn sc-btn-sm sc-btn-secondary lg:hidden"
            @click="emit('toggle-nav')"
        >
            <SIcon name="menu" :size="16" label="Open navigation" />
        </button>

        <!-- Account switcher — the tenant boundary, so it leads the bar. -->
        <Dropdown v-if="jetstream.hasTeamFeatures && currentTeam" align="left" width="60" class="min-w-0">
            <template #trigger>
                <button
                    type="button"
                    class="flex max-w-[240px] items-center gap-2.5 rounded-xl border px-2 py-1.5 transition-colors sm:px-2.5"
                    style="background: var(--s2); border-color: var(--line2)"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg text-[12px] font-extrabold"
                        style="background: var(--sc-accent); color: #fff"
                    >{{ teamInitials }}</span>
                    <span class="hidden min-w-0 text-left sm:block">
                        <span class="block truncate text-[13.5px] font-bold leading-tight text-t1">{{ currentTeam.name }}</span>
                        <span class="block text-[12px] leading-tight text-t4">{{ one('organization') }}</span>
                    </span>
                    <SIcon name="chevronDown" :size="14" class="hidden sm:block" />
                </button>
            </template>

            <template #content>
                <div class="w-60">
                    <div class="block px-4 py-2 text-[12px] font-semibold text-t4">Manage {{ one('organization').toLowerCase() }}</div>

                    <DropdownLink :href="route('teams.show', currentTeam)">Settings</DropdownLink>
                    <DropdownLink v-if="jetstream.canCreateTeams" :href="route('teams.create')">
                        New {{ one('organization').toLowerCase() }}
                    </DropdownLink>

                    <template v-if="allTeams.length > 1">
                        <div class="border-t" style="border-color: var(--line)" />
                        <div class="block px-4 py-2 text-[12px] font-semibold text-t4">Switch</div>

                        <form v-for="team in allTeams" :key="team.id" @submit.prevent="props.switchToTeam(team)">
                            <DropdownLink as="button">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-1.5 rounded-full"
                                        :style="{ background: team.id === user.current_team_id ? 'var(--ok)' : 'var(--line2)' }"
                                    ></span>
                                    {{ team.name }}
                                    <span v-if="team.id === user.current_team_id" class="sr-only">(current)</span>
                                </span>
                            </DropdownLink>
                        </form>
                    </template>
                </div>
            </template>
        </Dropdown>

        <div class="flex-1"></div>

        <Link
            :href="route('notifications.index')"
            class="sc-btn sc-btn-sm sc-btn-secondary relative shrink-0"
        >
            <SIcon name="bell" :size="16" :label="unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'" />
            <span
                v-if="unread > 0"
                class="absolute -right-1 -top-1 rounded-full px-1.5 text-[11px] font-bold leading-[17px] text-white"
                style="background: var(--bad)"
                aria-hidden="true"
            >{{ unread > 9 ? '9+' : unread }}</span>
        </Link>

        <button
            type="button"
            class="sc-btn sc-btn-sm sc-btn-secondary shrink-0"
            @click="toggleTheme"
        >
            <SIcon
                :name="theme === 'dark' ? 'sun' : 'moon'"
                :size="16"
                :label="`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`"
            />
            <span class="hidden sm:inline">{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
        </button>

        <Link :href="route('posts.create')" class="sc-btn sc-btn-primary sc-btn-sm shrink-0 sm:!px-4 sm:!py-2.5 sm:!text-[13px]">
            <SIcon name="plus" :size="15" :stroke-width="2.2" />
            <span class="hidden sm:inline">New post</span>
            <span class="sr-only sm:hidden">New post</span>
        </Link>

        <Dropdown align="right" width="48" class="shrink-0">
            <template #trigger>
                <button
                    v-if="jetstream.managesProfilePhotos && user.profile_photo_url"
                    type="button"
                    class="rounded-full border-2 border-transparent transition"
                    aria-label="Account menu"
                >
                    <img class="size-9 rounded-full object-cover" :src="user.profile_photo_url" :alt="user.name" />
                </button>
                <button
                    v-else
                    type="button"
                    class="flex size-9 items-center justify-center rounded-full text-[12px] font-extrabold"
                    style="background: var(--sc-accent-2); color: #fff"
                    aria-label="Account menu"
                >{{ initials }}</button>
            </template>

            <template #content>
                <div class="block px-4 py-2 text-[12px] font-semibold text-t4">
                    {{ user.name }}
                </div>

                <DropdownLink :href="route('profile.show')">Profile</DropdownLink>
                <DropdownLink :href="route('notifications.index')">Notification settings</DropdownLink>
                <DropdownLink v-if="jetstream.hasApiFeatures" :href="route('api-tokens.index')">API tokens</DropdownLink>

                <div class="border-t" style="border-color: var(--line)" />

                <form @submit.prevent="emit('logout')">
                    <DropdownLink as="button">Sign out</DropdownLink>
                </form>
            </template>
        </Dropdown>
    </header>
</template>
