<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';

const props = defineProps({
    notifications: { type: Object, required: true },
    unread: { type: Number, default: 0 },
    events: { type: Array, default: () => [] },
    digests: { type: Array, default: () => [] },
});

const tab = ref('inbox');

const form = useForm({
    preferences: props.events.map((event) => ({
        event: event.key,
        in_app: event.preference.in_app,
        email: event.preference.email,
        push: event.preference.push,
        digest: event.preference.digest,
    })),
});

const save = () => form.put(route('notifications.preferences'), { preserveScroll: true });

const markRead = (id) => router.post(route('notifications.read', id), {}, { preserveScroll: true });
const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });

const describe = (notification) => notification.data?.message
    ?? notification.data?.title
    ?? notification.type.replace(/([A-Z])/g, ' $1').trim();

const when = (value) => value
    ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
    : '';
</script>

<template>
    <AppLayout title="Notifications">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="sc-title">Notifications</h1>
                    <p class="sc-sub">
                        {{ unread > 0 ? `${unread} unread` : 'You are all caught up.' }}
                    </p>
                </div>
                <button v-if="unread > 0" type="button" class="sc-btn sc-btn-secondary" @click="markAllRead">
                    <SIcon name="check" :size="15" /> Mark all read
                </button>
            </div>

            <div class="flex gap-1.5" role="tablist" aria-label="Notification sections">
                <button type="button" role="tab" :aria-selected="tab === 'inbox'"
                        class="sc-pill" :class="tab === 'inbox' ? 'sc-pill-on' : ''" @click="tab = 'inbox'">
                    Inbox
                </button>
                <button type="button" role="tab" :aria-selected="tab === 'preferences'"
                        class="sc-pill" :class="tab === 'preferences' ? 'sc-pill-on' : ''" @click="tab = 'preferences'">
                    Preferences
                </button>
            </div>

            <!-- INBOX -->
            <div v-if="tab === 'inbox'">
                <div v-if="notifications.data.length" class="sc-card overflow-hidden">
                    <div
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        class="sc-row flex items-start gap-3 p-3.5"
                        :style="notification.read ? '' : 'background: var(--sc-soft)'"
                    >
                        <SIcon name="bell" :size="16" class="mt-0.5" />
                        <div class="min-w-0 flex-1">
                            <p class="text-[14px] font-semibold text-t1">{{ describe(notification) }}</p>
                            <p class="text-[12.5px] text-t3">
                                {{ when(notification.created) }}
                                <span v-if="!notification.read" class="font-bold" style="color: var(--sc-accent-text)"> · Unread</span>
                            </p>
                        </div>
                        <button
                            v-if="!notification.read"
                            type="button"
                            class="sc-btn sc-btn-sm sc-btn-ghost"
                            @click="markRead(notification.id)"
                        >
                            Mark read
                        </button>
                    </div>
                </div>

                <EmptyState
                    v-else
                    icon="bell"
                    title="No notifications"
                    description="Approval requests, failed publishes and expiring connections will show up here."
                />
            </div>

            <!-- PREFERENCES -->
            <form v-else class="sc-card overflow-hidden" @submit.prevent="save">
                <table class="w-full text-left">
                    <caption class="sr-only">Notification delivery preferences by event</caption>
                    <thead>
                        <tr class="sc-row">
                            <th scope="col" class="p-3 text-[12px] font-bold text-t4">Event</th>
                            <th scope="col" class="p-3 text-[12px] font-bold text-t4">In app</th>
                            <th scope="col" class="p-3 text-[12px] font-bold text-t4">Email</th>
                            <th scope="col" class="p-3 text-[12px] font-bold text-t4">Frequency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(preference, index) in form.preferences" :key="preference.event" class="sc-row">
                            <th scope="row" class="p-3 font-normal">
                                <p class="text-[14px] font-semibold text-t1">{{ events[index].label }}</p>
                                <p class="text-[12.5px] text-t3">{{ events[index].description }}</p>
                            </th>
                            <td class="p-3">
                                <input
                                    v-model="preference.in_app"
                                    type="checkbox"
                                    class="sc-check"
                                    :aria-label="`In-app notifications for ${events[index].label}`"
                                />
                            </td>
                            <td class="p-3">
                                <input
                                    v-model="preference.email"
                                    type="checkbox"
                                    class="sc-check"
                                    :aria-label="`Email notifications for ${events[index].label}`"
                                />
                            </td>
                            <td class="p-3">
                                <label class="sr-only" :for="`digest-${preference.event}`">Frequency for {{ events[index].label }}</label>
                                <select :id="`digest-${preference.event}`" v-model="preference.digest" class="sc-input max-w-[150px]">
                                    <option v-for="digest in digests" :key="digest" :value="digest">{{ digest }}</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex justify-end border-t p-3" style="border-color: var(--line)">
                    <button type="submit" class="sc-btn sc-btn-primary sc-btn-sm" :disabled="form.processing">
                        Save preferences
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
