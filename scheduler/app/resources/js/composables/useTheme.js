import { ref } from 'vue';

const STORAGE_KEY = 'scheduleistic.theme';

// The inline script in app.blade.php already stamped data-theme before first
// paint; start from whatever it decided so we never disagree with the DOM.
const initial = typeof document !== 'undefined'
    ? document.documentElement.getAttribute('data-theme') || 'dark'
    : 'dark';

const theme = ref(initial === 'light' ? 'light' : 'dark');

const apply = (value) => {
    if (typeof document === 'undefined') return;

    document.documentElement.setAttribute('data-theme', value);
    // Keep the pre-CSS background in sync too, otherwise a hard reload paints
    // the old theme's colour for a frame before the stylesheet lands.
    document.documentElement.style.background = value === 'light' ? '#F0F2FF' : '#070B14';
};

/**
 * App-wide light/dark theme, persisted per browser.
 *
 * The token layer in resources/css/app.css does all the actual work — this only
 * flips the `data-theme` attribute the tokens hang off.
 */
export function useTheme() {
    const setTheme = (value) => {
        theme.value = value === 'light' ? 'light' : 'dark';
        apply(theme.value);

        try {
            localStorage.setItem(STORAGE_KEY, theme.value);
        } catch (e) {
            // Private mode / storage disabled — the theme still applies for this page.
        }
    };

    const toggleTheme = () => setTheme(theme.value === 'dark' ? 'light' : 'dark');

    return { theme, setTheme, toggleTheme };
}
