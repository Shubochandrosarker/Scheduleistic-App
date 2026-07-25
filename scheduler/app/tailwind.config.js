import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class', '[data-theme="dark"]'],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },

            // Every colour resolves to a token in resources/css/app.css so a theme
            // switch or a tenant accent never needs a component edit.
            colors: {
                accent: {
                    DEFAULT: 'var(--sc-accent)',
                    bright: 'var(--sc-accent-bright)',
                    text: 'var(--sc-accent-text)',
                    soft: 'var(--sc-soft)',
                    mid: 'var(--sc-mid)',
                    2: 'var(--sc-accent-2)',
                },
                t1: 'var(--t1)',
                t2: 'var(--t2)',
                t3: 'var(--t3)',
                t4: 'var(--t4)',
                'on-accent': 'var(--on-acc)',
                line: 'var(--line)',
                line2: 'var(--line2)',
                hover: 'var(--hover)',
                s1: 'var(--s1)',
                s2: 'var(--s2)',
                s3: 'var(--s3)',
                page: 'var(--bg)',
                page2: 'var(--bg2)',
                ok: 'var(--ok)',
                warn: 'var(--warn)',
                bad: 'var(--bad)',
                alt: 'var(--alt)',
                cyan2: 'var(--cy-text)',
            },

            borderRadius: {
                '4xl': '1.75rem',
            },

            boxShadow: {
                card: '0 4px 14px var(--shadow-c)',
                lift: '0 16px 48px var(--shadow-d)',
                glow: '0 8px 24px var(--sc-mid)',
            },

            animation: {
                in: 'sc-in 0.28s ease both',
                blink: 'sc-blink 1.4s infinite',
            },
        },
    },

    plugins: [forms, typography],
};
