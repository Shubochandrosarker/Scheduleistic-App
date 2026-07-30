/**
 * The icon system.
 *
 * One 24×24 stroked grid, one source of truth. Before 2.0 icons were `d`
 * strings pasted inline into whatever component needed them, and functional
 * controls used emoji (↩ ☼ ▾) which screen readers announce as punctuation or
 * skip entirely. Every icon now lives here and is rendered through SIcon,
 * which enforces an accessible name on every icon-only control.
 *
 * Each entry is an array of path commands so multi-stroke icons stay one icon.
 */
export const ICONS = {
    // Navigation
    home: ['M3 11.5 12 4l9 7.5', 'M5.5 10v9.5h13V10'],
    activity: ['M3 12h4l3 8 4-16 3 8h4'],
    bell: ['M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7', 'M13.7 20a2 2 0 0 1-3.4 0'],
    calendar: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4', 'M16 3v4'],
    megaphone: ['M4 10v4h3l7 4V6l-7 4H4z', 'M18 9a3 3 0 0 1 0 6'],
    lightbulb: ['M9 18h6', 'M10 21h4', 'M12 3a6 6 0 0 0-3.5 10.9V16h7v-2.1A6 6 0 0 0 12 3z'],
    checkSquare: ['M4 4h16v16H4z', 'm8 12 3 3 5-6'],
    pencil: ['M4 20h16', 'M6 16 16 6l3 3L9 19H6z'],
    image: ['M4 5h16v14H4z', 'M4 15l4.5-4.5 4 4L16 11l4 4', 'M9 9.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0'],
    layers: ['m12 3 8 4.5-8 4.5-8-4.5L12 3z', 'm4 12 8 4.5 8-4.5', 'm4 16.5 8 4.5 8-4.5'],
    library: ['M4 5h4v14H4z', 'M10 5h4v14h-4z', 'm16.5 5.5 3.5 1-3.5 13-3.5-1z'],
    inbox: ['M4 5h16v14H4z', 'M4 13h4l1.5 2.5h5L16 13h4'],
    star: ['m12 4 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L4.2 9.7l5.4-.8z'],
    reply: ['M9 8 4 12l5 4', 'M4 12h9a5 5 0 0 1 5 5v2'],
    chart: ['M4 20V10', 'M10 20V4', 'M16 20v-7', 'M2 20h20'],
    report: ['M6 3h9l3 3v15H6z', 'M14 3v4h4', 'M9 12h6', 'M9 16h6'],
    target: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2'],
    users: ['M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2', 'M9.5 6.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0', 'M21 20v-2a4 4 0 0 0-3-3.9', 'M16 6.6a3 3 0 0 1 0 5.8'],
    plug: ['M9 3v6', 'M15 3v6', 'M7 9h10v3a5 5 0 0 1-10 0z', 'M12 17v4'],
    bolt: ['m13 3-8 10h6l-1 8 8-10h-6z'],
    palette: ['M12 21a9 9 0 1 1 0-18c5 0 9 3.6 9 8 0 2.2-1.8 4-4 4h-1.5a2 2 0 0 0-1.4 3.4A2 2 0 0 1 12 21z', 'M7.5 12.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2', 'M10.5 8.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2', 'M15.5 8.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2'],
    globe: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M3 12h18', 'M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z'],
    card: ['M3 7h18v11H3z', 'M3 11h18'],
    code: ['m9 8-5 4 5 4', 'm15 8 5 4-5 4'],
    settings: ['M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'M19.4 14a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V20a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 18.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4 12.6H4a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 5.7 5.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H11a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a2 2 0 1 1 0 4h-.1z'],
    shield: ['m12 3 8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z'],
    server: ['M4 4h16v6H4z', 'M4 14h16v6H4z', 'M7.5 7h.01', 'M7.5 17h.01'],
    building: ['M4 21V5l8-2v18', 'M12 9h8v12', 'M7 8h2', 'M7 12h2', 'M7 16h2', 'M15 13h2', 'M15 17h2'],

    // Actions
    plus: ['M12 5v14', 'M5 12h14'],
    minus: ['M5 12h14'],
    close: ['m6 6 12 12', 'm18 6-12 12'],
    check: ['m5 12.5 4.5 4.5L19 7'],
    search: ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z', 'm20 20-3.5-3.5'],
    filter: ['M4 5h16', 'M7 12h10', 'M10 19h4'],
    chevronDown: ['m6 9 6 6 6-6'],
    chevronUp: ['m6 15 6-6 6 6'],
    chevronLeft: ['m14 6-6 6 6 6'],
    chevronRight: ['m10 6 6 6-6 6'],
    menu: ['M4 6h16', 'M4 12h16', 'M4 18h16'],
    more: ['M6 12h.01', 'M12 12h.01', 'M18 12h.01'],
    trash: ['M4 7h16', 'M9 7V5h6v2', 'M6 7l1 13h10l1-13', 'M10 11v6', 'M14 11v6'],
    copy: ['M8 8h12v12H8z', 'M4 16V4h12'],
    upload: ['M12 16V4', 'm7 9 5-5 5 5', 'M4 17v3h16v-3'],
    download: ['M12 4v12', 'm7 11 5 5 5-5', 'M4 17v3h16v-3'],
    refresh: ['M20 11a8 8 0 0 0-14-4L4 9', 'M4 5v4h4', 'M4 13a8 8 0 0 0 14 4l2-2', 'M20 19v-4h-4'],
    external: ['M14 4h6v6', 'M20 4l-9 9', 'M18 14v6H4V6h6'],
    link: ['M10 13a4 4 0 0 0 6 .5l2-2a4 4 0 1 0-5.7-5.7L11 7', 'M14 11a4 4 0 0 0-6-.5l-2 2A4 4 0 1 0 11.7 18l1.3-1.3'],
    logout: ['M9 5H5v14h4', 'M15 8l4 4-4 4', 'M19 12H9'],
    sun: ['M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z', 'M12 2v2', 'M12 20v2', 'm4.9 4.9 1.4 1.4', 'm17.7 17.7 1.4 1.4', 'M2 12h2', 'M20 12h2', 'm4.9 19.1 1.4-1.4', 'm17.7 6.3 1.4-1.4'],
    moon: ['M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z'],
    grip: ['M9 6h.01', 'M9 12h.01', 'M9 18h.01', 'M15 6h.01', 'M15 12h.01', 'M15 18h.01'],

    // Status
    alert: ['M12 8v5', 'M12 17h.01', 'M12 3 2 20h20z'],
    info: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 11v5', 'M12 8h.01'],
    clock: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 7v5l3 2'],
    lock: ['M5 11h14v9H5z', 'M8 11V8a4 4 0 0 1 8 0v3'],
};

/** Path commands for an icon name; unknown names render nothing rather than throwing. */
export function iconPaths(name) {
    return ICONS[name] ?? [];
}
