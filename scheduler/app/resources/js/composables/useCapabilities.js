import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Plan entitlements, resolved server-side.
 *
 * The audit found plan logic leaking into components. Nothing in Vue may
 * compare plan names any more: it asks `can('media_library')`, and when the
 * answer is no it asks `upgradeFor('media_library')` for the plan that would
 * unlock it, so a locked control can explain itself instead of being a dead
 * grey box.
 *
 * This is presentation only. Every gated route also carries the `capability`
 * middleware server-side — hiding a link is not access control.
 */
export function useCapabilities() {
    const page = usePage();

    const capabilities = computed(() => page.props.capabilities ?? {});
    const upgrades = computed(() => page.props.upgradeFor ?? {});

    /** Whether the account is entitled to a capability. */
    const can = (capability) => Boolean(capabilities.value[capability]);

    /** The cheapest plan name that grants it, or null if already granted. */
    const upgradeFor = (capability) => (can(capability) ? null : upgrades.value[capability] ?? null);

    /** Ready-made copy for a locked control. */
    const lockedReason = (capability) => {
        const plan = upgradeFor(capability);

        return plan ? `Available on the ${plan} plan and above.` : null;
    };

    return { capabilities, can, upgradeFor, lockedReason };
}

/**
 * Customer-facing labels for internal model names, shared from
 * config/terminology.php. Templates never hard-code "Workspace" or "Channel".
 */
export function useTerms() {
    const page = usePage();

    const terms = computed(() => page.props.terms ?? {});

    const one = (key) => terms.value[key]?.one ?? key;
    const many = (key) => terms.value[key]?.many ?? `${key}s`;
    const lower = (key) => one(key).toLowerCase();

    return { terms, one, many, lower };
}
