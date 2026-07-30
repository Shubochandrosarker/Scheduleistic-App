import { ref } from 'vue';

const STORAGE_KEY = 'scheduleistic.theme';

// The inline script in app.blade.php already stamped data-theme before first
// paint; start from whatever it decided so we never disagree with the DOM.
//
// Light is the default because the marketing site is light: someone who clicks
// through from scheduleistic.com should not have the product invert on them.
// Dark remains a first-class theme, just no longer the one you land in.
const initial = typeof document !== 'undefined'
    ? document.documentElement.getAttribute('data-theme') || 'light'
    : 'light';

const theme = ref(initial === 'dark' ? 'dark' : 'light');

const apply = (value) => {
    if (typeof document === 'undefined') return;

    document.documentElement.setAttribute('data-theme', value);
    // Keep the pre-CSS background in sync too, otherwise a hard reload paints
    // the old theme's colour for a frame before the stylesheet lands. These two
    // values must stay equal to --bg in resources/css/app.css.
    document.documentElement.style.background = value === 'dark' ? '#08092F' : '#FFFFFF';
};

/**
 * App-wide light/dark theme, persisted per browser.
 *
 * The token layer in resources/css/app.css does all the actual work — this only
 * flips the `data-theme` attribute the tokens hang off.
 */
export function useTheme() {
    const setTheme = (value) => {
        theme.value = value === 'dark' ? 'dark' : 'light';
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
