import { ref, watch } from 'vue';

const STORAGE_KEY = 'scheduleistic.sidebar.compact';

// Module-level so every instance of the rail (desktop + mobile drawer) reads
// the same value without prop drilling through the layout.
const compact = ref(read());

function read() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

watch(compact, (value) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // Private browsing with storage disabled: the preference simply does
        // not persist. Not worth surfacing to the user.
    }
});

/** Persisted expanded/compact preference for the navigation rail. */
export function useSidebar() {
    return {
        compact,
        toggleCompact: () => { compact.value = !compact.value; },
    };
}
