/**
 * The application's information architecture.
 *
 * One declarative tree drives the desktop rail, the mobile drawer and the
 * mobile bottom bar, so the three can never disagree about what exists or what
 * is active. Before 2.0 this was a flat seven-item array inline in the sidebar
 * component.
 *
 * Per item:
 *   route       ziggy route name (also the link target)
 *   match       ziggy pattern that keeps the item lit anywhere in the section
 *   icon        key in icons.js
 *   capability  entitlement required; absent means always available
 *   admin       platform-admin only
 *   badge       shared prop name whose count renders as a badge
 *   soon        declared-but-unbuilt destination (rendered as roadmap, never
 *               as a dead link — the 2.0 brief forbids navigation that goes
 *               nowhere)
 */
export const NAV_GROUPS = [
    {
        label: 'Home',
        items: [
            { label: 'Overview', route: 'dashboard', match: 'dashboard', icon: 'home' },
            { label: 'Notifications', route: 'notifications.index', match: 'notifications.*', icon: 'bell', badge: 'unreadNotifications' },
        ],
    },
    {
        label: 'Plan',
        items: [
            { label: 'Calendar', route: 'planner.index', match: 'planner.*', icon: 'calendar' },
            { label: 'Campaigns', route: 'campaigns.index', match: 'campaigns.*', icon: 'megaphone', capability: 'campaigns' },
            { label: 'Ideas', route: 'ideas.index', match: 'ideas.*', icon: 'lightbulb', capability: 'ideas' },
        ],
    },
    {
        label: 'Create',
        items: [
            { label: 'Composer', route: 'posts.create', match: 'posts.create', icon: 'pencil' },
            { label: 'Media library', route: 'media.index', match: 'media.*', icon: 'image', capability: 'media_library' },
            { label: 'Bulk import', route: 'workspaces.index', match: 'workspaces.import*', icon: 'upload' },
        ],
    },
    {
        label: 'Engage',
        items: [
            { label: 'Social inbox', icon: 'inbox', capability: 'social_inbox', soon: true },
            { label: 'Saved replies', icon: 'reply', capability: 'saved_replies', soon: true },
        ],
    },
    {
        label: 'Measure',
        items: [
            { label: 'Analytics', route: 'analytics.index', match: 'analytics.*', icon: 'chart' },
            { label: 'Reports', icon: 'report', capability: 'reports', soon: true },
            { label: 'Competitors', icon: 'target', capability: 'competitor_tracking', soon: true },
        ],
    },
    {
        label: 'Manage',
        items: [
            { label: 'Brands', route: 'workspaces.index', match: 'workspaces.index', icon: 'layers', term: 'workspace' },
            { label: 'Social profiles', route: 'channels.health', match: 'channels.*', icon: 'plug', term: 'channel' },
            { label: 'Team', route: 'teams.show', match: 'teams.*', icon: 'users', param: 'currentTeam' },
        ],
    },
    {
        label: 'Account',
        items: [
            { label: 'Branding', route: 'branding.edit', match: 'branding.*', icon: 'palette' },
            { label: 'Billing', route: 'billing.index', match: 'billing.*', icon: 'card' },
            { label: 'API tokens', route: 'api-tokens.index', match: 'api-tokens.*', icon: 'code' },
            { label: 'Webhooks', route: 'webhooks.index', match: 'webhooks.*', icon: 'bolt', capability: 'webhooks' },
            { label: 'Settings', route: 'profile.show', match: 'profile.*', icon: 'settings' },
        ],
    },
    {
        label: 'Platform admin',
        admin: true,
        items: [
            { label: 'Organizations', route: 'admin.organizations.index', match: 'admin.*', icon: 'building', admin: true },
        ],
    },
];

/** The five destinations on the mobile bottom bar. Compose sits centre. */
export const MOBILE_NAV = [
    { label: 'Home', route: 'dashboard', match: 'dashboard', icon: 'home' },
    { label: 'Calendar', route: 'planner.index', match: 'planner.*', icon: 'calendar' },
    { label: 'Compose', route: 'posts.create', match: 'posts.create', icon: 'plus', primary: true },
    { label: 'Alerts', route: 'notifications.index', match: 'notifications.*', icon: 'bell', badge: 'unreadNotifications' },
];
