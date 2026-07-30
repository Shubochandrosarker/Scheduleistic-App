<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SIcon from '@/Components/UI/SIcon.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { useTerms } from '@/composables/useCapabilities';

/**
 * The media library.
 *
 * Uploads are queued client-side and posted one at a time so a dropped batch
 * of twenty files does not open twenty parallel connections. Each upload then
 * polls its own processing status, because the server does the expensive work
 * (probe, scan, derivatives) after the response has already been sent.
 */
const props = defineProps({
    assets: { type: Object, required: true },
    folders: { type: Array, default: () => [] },
    workspaces: { type: Array, default: () => [] },
    quota: { type: Object, default: null },
    sources: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { one, many } = useTerms();

const workspaceId = ref(props.filters.workspace_id ?? props.workspaces[0]?.id ?? null);
const search = ref(props.filters.search ?? '');
const kind = ref(props.filters.kind ?? '');
const dragOver = ref(false);
const fileInput = ref(null);
const uploads = ref([]);   // { name, state, error }
const selectedAsset = ref(null);

const pollTimers = new Set();
onBeforeUnmount(() => pollTimers.forEach(clearTimeout));

const applyFilters = () => router.get(route('media.index'), {
    workspace_id: workspaceId.value || undefined,
    search: search.value || undefined,
    kind: kind.value || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });

// --- Upload ------------------------------------------------------------

const upload = (files) => {
    if (!workspaceId.value) {
        window.alert(`Choose a ${one('workspace').toLowerCase()} before uploading.`);
        return;
    }

    // Sequential, not parallel: one large video should not starve the rest.
    const queue = Array.from(files);

    const next = () => {
        const file = queue.shift();
        if (!file) return;

        const entry = { name: file.name, state: 'uploading', error: null };
        uploads.value = [...uploads.value, entry];

        const form = useForm({ workspace_id: workspaceId.value, file });

        form.post(route('media.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                entry.state = 'processing';
                router.reload({ only: ['assets', 'quota'] });
                pollNewest(entry);
                next();
            },
            onError: (errors) => {
                entry.state = 'failed';
                entry.error = errors.file ?? Object.values(errors)[0] ?? 'Upload failed.';
                next();
            },
        });
    };

    next();
};

/**
 * After an upload the newest asset in the list is the one just created; poll
 * it until the queue reports ready or failed.
 */
const pollNewest = (entry) => {
    const asset = props.assets.data?.[0];
    if (!asset) { entry.state = 'done'; return; }

    let attempts = 0;

    const check = async () => {
        attempts += 1;

        try {
            const response = await fetch(route('media.status', asset.id), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (data.status === 'ready') {
                entry.state = 'done';
                router.reload({ only: ['assets'] });
                return;
            }

            if (data.status === 'failed') {
                entry.state = 'failed';
                entry.error = data.error ?? 'Processing failed.';
                return;
            }
        } catch {
            // Transient network problem; the retry below covers it.
        }

        // Give up after ~30s of polling; the library still shows the asset
        // with its real status, so nothing is lost.
        if (attempts < 15) {
            const timer = setTimeout(check, 2000);
            pollTimers.add(timer);
        } else {
            entry.state = 'done';
        }
    };

    const timer = setTimeout(check, 1500);
    pollTimers.add(timer);
};

const onDrop = (event) => {
    dragOver.value = false;
    upload(event.dataTransfer.files);
};

// --- Asset actions -----------------------------------------------------

const toggleFavorite = (asset) => router.put(route('media.update', asset.id), {
    is_favorite: !asset.is_favorite,
}, { preserveScroll: true });

const saveAltText = (asset, value) => router.put(route('media.update', asset.id), {
    alt_text: value,
}, { preserveScroll: true, onSuccess: () => { selectedAsset.value = null; } });

const remove = (asset) => {
    if (!window.confirm(`Delete “${asset.name}”? Posts already using it keep their copy.`)) return;

    router.delete(route('media.destroy', asset.id), { preserveScroll: true });
};

const activeUploads = computed(() => uploads.value.filter((u) => u.state === 'uploading' || u.state === 'processing'));
</script>

<template>
    <AppLayout title="Media library">
        <div class="flex flex-col gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="sc-title">Media library</h1>
                    <p class="sc-sub">Images and video, organised per {{ one('workspace').toLowerCase() }}.</p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        class="sr-only"
                        accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
                        @change="upload($event.target.files)"
                    />
                    <button type="button" class="sc-btn sc-btn-primary" @click="fileInput.click()">
                        <SIcon name="upload" :size="15" /> Upload
                    </button>
                </div>
            </div>

            <!-- Storage quota -->
            <div v-if="quota" class="sc-card p-3.5">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <span class="text-[13px] font-bold text-t2">Storage</span>
                    <span class="text-[13px] font-semibold text-t3">{{ quota.used_label }} of {{ quota.quota_label }}</span>
                </div>
                <div
                    v-if="quota.percent !== null"
                    class="h-1.5 overflow-hidden rounded-full"
                    style="background: var(--line)"
                    role="progressbar"
                    :aria-valuenow="quota.percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`Storage used: ${quota.used_label} of ${quota.quota_label}`"
                >
                    <div
                        class="h-full rounded-full"
                        :style="{ width: quota.percent + '%', background: quota.percent > 90 ? 'var(--bad)' : 'var(--sc-accent)' }"
                    ></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <label class="sr-only" for="media-workspace">{{ one('workspace') }}</label>
                <select id="media-workspace" v-model="workspaceId" class="sc-input max-w-[220px]" @change="applyFilters">
                    <option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
                </select>

                <label class="sr-only" for="media-kind">Type</label>
                <select id="media-kind" v-model="kind" class="sc-input max-w-[160px]" @change="applyFilters">
                    <option value="">All types</option>
                    <option value="image">Images</option>
                    <option value="video">Video</option>
                </select>

                <div class="flex min-w-[200px] flex-1 items-center gap-2 rounded-xl border px-3"
                     style="background: var(--s3); border-color: var(--line2)">
                    <SIcon name="search" :size="15" />
                    <label class="sr-only" for="media-search">Search assets</label>
                    <input
                        id="media-search"
                        v-model="search"
                        type="search"
                        class="w-full border-0 bg-transparent py-2.5 text-[14px] text-t1 outline-none placeholder:text-t4"
                        placeholder="Search by name or alt text"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </div>

            <!-- Import sources: only offered when actually configured. -->
            <div v-if="sources.length" class="flex flex-wrap items-center gap-2">
                <span class="text-[12px] font-bold text-t4">Import from</span>
                <span
                    v-for="source in sources"
                    :key="source.key"
                    class="sc-pill cursor-default"
                    :title="source.configured
                        ? `${source.label} import is configured on this installation.`
                        : `${source.label} import needs credentials in the environment before it can be used.`"
                >
                    {{ source.label }}
                    <span v-if="!source.configured" class="text-t4">· not configured</span>
                </span>
            </div>

            <!-- Upload progress -->
            <div v-if="uploads.length" class="sc-card flex flex-col gap-2 p-3.5" aria-live="polite">
                <p class="text-[12px] font-bold text-t4">
                    Uploads {{ activeUploads.length ? `(${activeUploads.length} in progress)` : '' }}
                </p>
                <div v-for="(entry, index) in uploads" :key="index" class="flex items-center gap-2 text-[13.5px]">
                    <SIcon
                        :name="entry.state === 'failed' ? 'alert' : entry.state === 'done' ? 'check' : 'clock'"
                        :size="14"
                        :style="{ color: entry.state === 'failed' ? 'var(--bad)' : entry.state === 'done' ? 'var(--ok)' : 'var(--t3)' }"
                    />
                    <span class="min-w-0 flex-1 truncate text-t2">{{ entry.name }}</span>
                    <span class="text-[12.5px] font-semibold text-t3">
                        {{ entry.state === 'processing' ? 'Processing…' : entry.state }}
                    </span>
                    <span v-if="entry.error" class="text-[12.5px]" style="color: var(--bad)">{{ entry.error }}</span>
                </div>
            </div>

            <!-- Drop zone + grid -->
            <div
                class="rounded-2xl border-2 border-dashed p-3 transition-colors"
                :style="{ borderColor: dragOver ? 'var(--sc-accent)' : 'var(--line)', background: dragOver ? 'var(--sc-soft)' : 'transparent' }"
                @dragover.prevent="dragOver = true"
                @dragleave="dragOver = false"
                @drop.prevent="onDrop"
            >
                <p class="mb-3 text-center text-[13px] text-t3">
                    Drop files here, or use Upload. JPEG, PNG, WebP, GIF, MP4, MOV and WebM are accepted.
                </p>

                <div v-if="assets.data.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <figure v-for="asset in assets.data" :key="asset.id" class="sc-card overflow-hidden">
                        <button
                            type="button"
                            class="relative block aspect-square w-full"
                            style="background: var(--s2)"
                            @click="selectedAsset = selectedAsset?.id === asset.id ? null : asset"
                        >
                            <img
                                v-if="asset.kind === 'image' && asset.thumb_url"
                                :src="asset.thumb_url"
                                :alt="asset.alt_text || asset.name"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <span v-else class="flex size-full items-center justify-center text-t3">
                                <SIcon :name="asset.kind === 'video' ? 'library' : 'image'" :size="26" />
                            </span>

                            <span
                                v-if="asset.processing_status !== 'ready'"
                                class="absolute inset-x-0 bottom-0 px-2 py-1 text-[12px] font-bold"
                                :style="{
                                    background: asset.processing_status === 'failed' ? 'rgba(248,113,113,0.9)' : 'rgba(0,0,0,0.6)',
                                    color: '#fff',
                                }"
                            >{{ asset.processing_status === 'failed' ? 'Failed' : 'Processing…' }}</span>
                        </button>

                        <figcaption class="p-2">
                            <p class="truncate text-[13px] font-semibold text-t1" :title="asset.name">{{ asset.name }}</p>
                            <p class="text-[12px] text-t3">
                                {{ asset.size_label }}
                                <span v-if="asset.width">· {{ asset.width }}×{{ asset.height }}</span>
                            </p>
                            <p v-if="!asset.alt_text" class="mt-0.5 text-[12px] font-semibold" style="color: var(--warn)">
                                No alt text
                            </p>

                            <div class="mt-2 flex items-center gap-1">
                                <button
                                    type="button"
                                    class="sc-btn sc-btn-sm sc-btn-ghost !px-2"
                                    :aria-pressed="asset.is_favorite"
                                    @click="toggleFavorite(asset)"
                                >
                                    <SIcon name="star" :size="14"
                                           :label="asset.is_favorite ? `Unfavourite ${asset.name}` : `Favourite ${asset.name}`"
                                           :style="asset.is_favorite ? 'color: var(--warn)' : ''" />
                                </button>
                                <button type="button" class="sc-btn sc-btn-sm sc-btn-ghost !px-2" @click="remove(asset)">
                                    <SIcon name="trash" :size="14" :label="`Delete ${asset.name}`" />
                                </button>
                            </div>

                            <!-- Alt text editor, inline. Accessibility metadata
                                 belongs next to the asset, not in a separate screen. -->
                            <div v-if="selectedAsset?.id === asset.id" class="mt-2">
                                <label class="sc-label" :for="`alt-${asset.id}`">Alt text</label>
                                <textarea
                                    :id="`alt-${asset.id}`"
                                    class="sc-input text-[13px]"
                                    rows="2"
                                    :value="asset.alt_text"
                                    @blur="saveAltText(asset, $event.target.value)"
                                ></textarea>
                            </div>
                        </figcaption>
                    </figure>
                </div>

                <EmptyState
                    v-else
                    icon="image"
                    title="No assets yet"
                    description="Upload images and video once, then reuse them across every post and network."
                />
            </div>

            <!-- Pagination -->
            <nav v-if="assets.links?.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="Pagination">
                <component
                    :is="link.url ? 'a' : 'span'"
                    v-for="link in assets.links"
                    :key="link.label"
                    :href="link.url"
                    class="sc-pill"
                    :class="link.active ? 'sc-pill-on' : ''"
                    :aria-current="link.active ? 'page' : undefined"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AppLayout>
</template>
