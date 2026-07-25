<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import SCard from '@/Components/UI/SCard.vue';
import SButton from '@/Components/UI/SButton.vue';
import SBadge from '@/Components/UI/SBadge.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    branding: Object,
    customDomain: String,
    domainVerified: Boolean,
    verifyToken: String,
    txtRecordName: String,
    cnameTarget: String,
});

const form = useForm({
    name: props.branding.name ?? '',
    tagline: props.branding.tagline ?? '',
    primary: props.branding.primary ?? '#4F8DFF',
    secondary: props.branding.secondary ?? '#22D3EE',
    logo: props.branding.logo ?? '',
});

const domainForm = useForm({ custom_domain: props.customDomain ?? '' });

const save = () => form.put(route('branding.update'), { preserveScroll: true });
const saveDomain = () => domainForm.put(route('branding.domain'), { preserveScroll: true });
const verifyDomain = () => router.post(route('branding.domain.verify'), {}, { preserveScroll: true });

// Ready-made accents. Picking one writes the field; the preview retints live
// because the whole UI reads the same --sc-* tokens.
const SWATCHES = ['#4F8DFF', '#00C04B', '#6C5CE7', '#F59E0B', '#E1306C', '#22D3EE'];

const initial = computed(() => (form.name || 'S').charAt(0).toUpperCase());
</script>

<template>
    <AppLayout title="White-label">
        <PageHeader eyebrow="Tenant branding">
            <template #title>White-label</template>
            <template #subtitle>
                Your product, your domain, your colours. Clients never see the platform name —
                including in transactional email and the OAuth consent screen.
            </template>
            <template #actions>
                <SBadge :tone="domainVerified ? 'ok' : 'neutral'">
                    {{ domainVerified ? 'Domain verified' : 'Platform domain' }}
                </SBadge>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- ── Brand identity ───────────────────────────────────────── -->
            <form class="space-y-4" @submit.prevent="save">
                <SCard title="Brand identity">
                    <div class="space-y-4">
                        <div>
                            <label class="sc-label" for="name">Product name</label>
                            <input id="name" v-model="form.name" type="text" class="sc-input" placeholder="Scheduleistic" />
                            <InputError :message="form.errors.name" class="mt-1.5" />
                        </div>

                        <div>
                            <label class="sc-label" for="tagline">Tagline</label>
                            <input id="tagline" v-model="form.tagline" type="text" class="sc-input" />
                            <InputError :message="form.errors.tagline" class="mt-1.5" />
                        </div>

                        <div>
                            <label class="sc-label">Accent colour</label>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <button
                                    v-for="c in SWATCHES"
                                    :key="c"
                                    type="button"
                                    class="size-10 rounded-xl transition-transform hover:scale-105"
                                    :style="{
                                        background: c,
                                        border: `2px solid ${form.primary.toLowerCase() === c.toLowerCase() ? 'var(--t1)' : 'transparent'}`,
                                    }"
                                    :aria-label="`Use ${c}`"
                                    @click="form.primary = c"
                                ></button>

                                <input
                                    v-model="form.primary"
                                    type="color"
                                    class="h-10 w-14 cursor-pointer rounded-xl border-0 bg-transparent p-0"
                                    aria-label="Custom accent colour"
                                />
                            </div>
                            <InputError :message="form.errors.primary" class="mt-1.5" />
                        </div>

                        <div>
                            <label class="sc-label" for="secondary">Secondary colour</label>
                            <div class="flex items-center gap-3">
                                <input
                                    id="secondary"
                                    v-model="form.secondary"
                                    type="color"
                                    class="h-10 w-14 cursor-pointer rounded-xl border-0 bg-transparent p-0"
                                />
                                <span class="font-mono text-[12.5px] uppercase text-t3">{{ form.secondary }}</span>
                                <span
                                    class="ml-auto h-8 w-24 rounded-lg"
                                    :style="{ background: `linear-gradient(135deg, ${form.primary}, ${form.secondary})` }"
                                    title="Gradient preview"
                                ></span>
                            </div>
                            <InputError :message="form.errors.secondary" class="mt-1.5" />
                        </div>

                        <div>
                            <label class="sc-label" for="logo">Logo URL</label>
                            <input id="logo" v-model="form.logo" type="url" class="sc-input" placeholder="https://…" />
                            <p class="mt-1.5 text-[11.5px] text-t4">Shown on login, in the sidebar, and on client emails.</p>
                            <InputError :message="form.errors.logo" class="mt-1.5" />
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <SButton type="submit" variant="primary" :disabled="form.processing">Save branding</SButton>
                    </div>
                </SCard>
            </form>

            <!-- ── Preview + custom domain ──────────────────────────────── -->
            <div class="space-y-4">
                <SCard title="Tenant preview" subtitle="What your client sees when they sign in.">
                    <div class="overflow-hidden rounded-xl border" style="border-color: var(--line2); background: var(--s1)">
                        <div class="flex items-center gap-2 border-b px-3.5 py-2.5" style="border-color: var(--line)">
                            <span class="size-2.5 rounded-full" style="background: #F87171"></span>
                            <span class="size-2.5 rounded-full" style="background: #F59E0B"></span>
                            <span class="size-2.5 rounded-full" style="background: #34D399"></span>
                            <span class="ml-2 truncate font-mono text-[11.5px] text-t4">
                                {{ customDomain || 'app.scheduleistic.com' }}
                            </span>
                        </div>

                        <div class="p-5">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex size-9 items-center justify-center rounded-xl text-[14px] font-extrabold"
                                    :style="{
                                        background: `linear-gradient(135deg, ${form.primary}, ${form.secondary})`,
                                        color: '#fff',
                                    }"
                                >{{ initial }}</span>
                                <span class="text-[16px] font-extrabold tracking-[-0.02em] text-t1">
                                    {{ form.name || 'Scheduleistic' }}
                                </span>
                            </div>

                            <p class="mt-2 text-[12.5px] text-t3">{{ form.tagline || 'Schedule posts. Manage clients.' }}</p>

                            <div class="mt-5 grid grid-cols-2 gap-2.5">
                                <div class="rounded-xl border p-3.5" :style="{ background: 'var(--s2)', borderColor: form.primary + '55' }">
                                    <p class="text-[22px] font-extrabold leading-none" :style="{ color: form.primary }">47</p>
                                    <p class="mt-1.5 text-[11.5px] text-t4">Scheduled</p>
                                </div>
                                <div class="rounded-xl border p-3.5" style="background: var(--s2); border-color: var(--line)">
                                    <p class="text-[22px] font-extrabold leading-none text-t1">12</p>
                                    <p class="mt-1.5 text-[11.5px] text-t4">In review</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="mt-3 w-full rounded-xl py-2.5 text-[13px] font-bold text-white"
                                :style="{ background: `linear-gradient(135deg, ${form.primary}, ${form.secondary})` }"
                            >Schedule a post</button>
                        </div>
                    </div>
                </SCard>

                <form @submit.prevent="saveDomain">
                    <SCard title="Custom domain" subtitle="HTTPS is issued automatically once the domain verifies.">
                        <div>
                            <label class="sc-label" for="domain">Domain</label>
                            <div class="flex flex-wrap gap-2.5">
                                <input
                                    id="domain"
                                    v-model="domainForm.custom_domain"
                                    type="text"
                                    class="sc-input min-w-[200px] flex-1"
                                    placeholder="app.youragency.com"
                                />
                                <SButton
                                    v-if="customDomain && !domainVerified"
                                    variant="secondary"
                                    @click="verifyDomain"
                                >Verify DNS</SButton>
                            </div>
                            <InputError :message="domainForm.errors.custom_domain" class="mt-1.5" />
                        </div>

                        <div
                            v-if="customDomain && domainVerified"
                            class="mt-4 flex items-start gap-2.5 rounded-xl border p-3.5"
                            style="background: rgba(52,211,153,0.1); border-color: rgba(52,211,153,0.3)"
                        >
                            <span class="text-[14px]">🔒</span>
                            <p class="text-[12.5px] leading-relaxed text-t2">
                                <span class="font-bold" style="color: var(--ok)">Verified.</span>
                                Certificates are fetched on demand and renew automatically.
                            </p>
                        </div>

                        <div v-else-if="customDomain" class="mt-4 space-y-2.5">
                            <p class="text-[12.5px] font-bold" style="color: var(--warn)">Pending verification</p>
                            <p class="text-[12.5px] leading-relaxed text-t3">Add these two DNS records, then hit verify:</p>

                            <dl class="space-y-1.5">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <dt class="w-16 shrink-0 text-[11px] font-bold uppercase tracking-wider text-t4">TXT</dt>
                                    <dd class="min-w-0 break-all"><code class="sc-code">{{ txtRecordName }}</code></dd>
                                </div>
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <dt class="w-16 shrink-0 text-[11px] font-bold uppercase tracking-wider text-t4">Value</dt>
                                    <dd class="min-w-0 break-all"><code class="sc-code">{{ verifyToken }}</code></dd>
                                </div>
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <dt class="w-16 shrink-0 text-[11px] font-bold uppercase tracking-wider text-t4">CNAME</dt>
                                    <dd class="min-w-0 break-all"><code class="sc-code">→ {{ cnameTarget }}</code></dd>
                                </div>
                            </dl>
                        </div>

                        <p v-else class="mt-4 text-[12.5px] leading-relaxed text-t3">
                            Point a CNAME at <code class="sc-code">{{ cnameTarget }}</code>, then enter the domain above.
                        </p>

                        <div class="mt-5 flex justify-end">
                            <SButton type="submit" variant="primary" :disabled="domainForm.processing">Save domain</SButton>
                        </div>
                    </SCard>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
