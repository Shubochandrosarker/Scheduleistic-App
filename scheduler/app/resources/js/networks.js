/**
 * Presentation metadata for the 13 social networks the publishing engine drives.
 *
 * Keys match the driver keys returned by ProviderManager, so anything the API
 * hands us (`channel.provider`, `target.provider`, a CSV import column) can be
 * rendered without a translation step. `short` is the 1–3 character glyph shown
 * in the square provider chip; `limit` is the network's caption cap, used by the
 * composer's character counter.
 */
export const NETWORKS = {
    instagram: { label: 'Instagram', short: 'IG', color: '#E1306C', limit: 2200, ratio: '1 / 1', crop: '1080×1080' },
    facebook: { label: 'Facebook', short: 'f', color: '#1877F2', limit: 5000, ratio: '1.91 / 1', crop: '1200×630' },
    linkedin: { label: 'LinkedIn', short: 'in', color: '#0A66C2', limit: 3000, ratio: '1.91 / 1', crop: '1200×627' },
    linkedin_company: { label: 'LinkedIn Page', short: 'in+', color: '#0A66C2', limit: 3000, ratio: '1.91 / 1', crop: '1200×627' },
    tiktok: { label: 'TikTok', short: 'TT', color: '#22D3EE', limit: 2200, ratio: '9 / 16', crop: '1080×1920' },
    google_business: { label: 'Google Business', short: 'GBP', color: '#34D399', limit: 1500, ratio: '4 / 3', crop: '1200×900' },
    youtube: { label: 'YouTube', short: 'YT', color: '#F87171', limit: 5000, ratio: '16 / 9', crop: '1280×720' },
    pinterest: { label: 'Pinterest', short: 'P', color: '#E60023', limit: 500, ratio: '2 / 3', crop: '1000×1500' },
    threads: { label: 'Threads', short: 'Th', color: '#C7CEE0', limit: 500, ratio: '1 / 1', crop: '1080×1080' },
    bluesky: { label: 'Bluesky', short: 'BS', color: '#4F8DFF', limit: 300, ratio: '1 / 1', crop: '1080×1080' },
    mastodon: { label: 'Mastodon', short: 'Ma', color: '#A78BFA', limit: 500, ratio: '1 / 1', crop: '1080×1080' },
    medium: { label: 'Medium', short: 'Md', color: '#F5F7FF', limit: 9000, ratio: '2 / 1', crop: '1400×700' },
    wordpress: { label: 'WordPress', short: 'W', color: '#8E78F2', limit: 9000, ratio: '16 / 9', crop: '1200×675' },
    website: { label: 'Website feed', short: 'Web', color: '#8B92A9', limit: 9000, ratio: '16 / 9', crop: '1200×675' },
    fake: { label: 'Demo channel', short: 'Dm', color: '#8B92A9', limit: 5000, ratio: '1 / 1', crop: '1080×1080' },
};

const FALLBACK = { label: 'Channel', short: '•', color: '#8B92A9', limit: 5000, ratio: '1 / 1', crop: '1080×1080' };

/** Metadata for a provider key, never null — unknown drivers get a neutral chip. */
export function network(key) {
    if (!key) return FALLBACK;
    return NETWORKS[key] ?? { ...FALLBACK, label: String(key).replace(/_/g, ' ') };
}

/** The network's colour at a given alpha, for tinted chip backgrounds. */
export function networkSoft(key, alpha = 0.16) {
    const hex = network(key).color.replace('#', '');
    const full = hex.length === 3 ? hex.split('').map((c) => c + c).join('') : hex;
    const n = Number.parseInt(full, 16);

    if (Number.isNaN(n)) return `rgba(139,146,169,${alpha})`;

    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
}
