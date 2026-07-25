import { watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const DEFAULT_ACCENT = '#4F8DFF';
const DEFAULT_SECONDARY = '#22D3EE';

const toRgb = (hex) => {
    const h = String(hex || DEFAULT_ACCENT).replace('#', '');
    const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
    const n = Number.parseInt(full, 16);

    return Number.isNaN(n)
        ? { r: 79, g: 141, b: 255 }
        : { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
};

const rgba = (hex, a) => {
    const { r, g, b } = toRgb(hex);
    return `rgba(${r},${g},${b},${a})`;
};

const lighten = (hex, amt) => {
    const { r, g, b } = toRgb(hex);
    const f = (v) => Math.min(255, Math.round(v + (255 - v) * amt));
    return `rgb(${f(r)},${f(g)},${f(b)})`;
};

const darken = (hex, amt) => {
    const { r, g, b } = toRgb(hex);
    const f = (v) => Math.max(0, Math.round(v * (1 - amt)));
    return `rgb(${f(r)},${f(g)},${f(b)})`;
};

/** A hex colour we're willing to write into the stylesheet. */
const isHex = (value) => /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value ?? ''));

/**
 * Repaints the accent token group from the tenant's white-label branding.
 *
 * Every accent surface in the app reads --sc-*, so setting these six variables
 * retints the entire product — sidebar, buttons, pills, charts — with no
 * per-component branching. Falls back to the platform blue when a tenant has
 * not set a colour (or set an unparseable one).
 */
export function useBrand() {
    const page = usePage();
    const { theme } = useTheme();

    watchEffect(() => {
        if (typeof document === 'undefined') return;

        const branding = page.props.branding ?? {};
        const accent = isHex(branding.primary) ? branding.primary : DEFAULT_ACCENT;
        const secondary = isHex(branding.secondary) ? branding.secondary : DEFAULT_SECONDARY;
        // Tracked so flipping the theme recomputes the readable accent text colour.
        const isLight = theme.value === 'light';

        const style = document.documentElement.style;
        style.setProperty('--sc-accent', accent);
        style.setProperty('--sc-accent-bright', lighten(accent, 0.18));
        // On a light page the raw accent is too pale for text, so darken instead.
        style.setProperty('--sc-accent-text', isLight ? darken(accent, 0.3) : lighten(accent, 0.18));
        style.setProperty('--sc-accent-2', secondary);
        style.setProperty('--sc-soft', rgba(accent, 0.12));
        style.setProperty('--sc-mid', rgba(accent, 0.28));
        style.setProperty('--sc-glow', rgba(accent, 0.4));
    });
}
