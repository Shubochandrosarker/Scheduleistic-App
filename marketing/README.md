# Scheduleistic — Marketing Site

The production build of **scheduleistic.com**.

> **This directory is a compiled artefact, not source.** It is the `dist/`
> output of the Astro project that generates the site. Editing the HTML here
> works for a one-off fix, but the next build overwrites it — change the Astro
> source and re-export instead.
>
> The previous contents of this directory (hand-written HTML with a single
> stylesheet, on a dark forest-green palette) were replaced by this build. If
> you need them, they are in git history before this commit.

## What's here

```
index.html            home
404.html
_astro/               the single compiled stylesheet
_headers, _redirects  host configuration (Netlify/Cloudflare Pages style)
brand/                logos, favicons, og image, web manifest
about/ contact/ demo/ help/ security/ status/ changelog/ roadmap/
features/ product/ pricing/ integrations/ resources/
blog/ glossary/ guides/ templates/
compare/              per-competitor alternative pages
legal/                terms, privacy, cookies, DPA, subprocessors, …
networks/             one page per supported social network
solutions/            one page per audience segment
llms.txt              machine-readable product summary
robots.txt, sitemap-index.xml, sitemap-0.xml
```

## Deploying

Static files, no build step at this stage — publish the directory as-is.

`_headers` and `_redirects` are consumed by Netlify and Cloudflare Pages. On
nginx or Caddy you must translate them by hand; nothing reads them
automatically.

Preview locally:

```bash
cd marketing && python3 -m http.server 8080
```

## Relationship to the app

The app in `scheduler/app` shares this site's design system, so a visitor
crossing from the marketing site into the product does not meet a second
design. Specifically:

| | |
| --- | --- |
| Palette | `--brand-*`, `--navy-*`, `--blue` are transcribed into `scheduler/app/resources/css/app.css` |
| Type | Plus Jakarta Sans on both |
| Buttons | 12px radius, 44px min height, weight 800, `135deg` blue→indigo gradient primary |
| Cards | 18px radius, 1px border, soft lifted shadow, 2px rise on hover |
| Focus ring | 3px accent at 45% alpha, 3px offset |
| Brand marks | `brand/` is copied into `scheduler/app/public/brand/` |

The app additionally carries a **dark theme** derived from the same navy
family, which this site does not have. If you restyle the site, mirror the
change in the app's `:root` block and check the `[data-theme='dark']` block
still holds up.

White-label tenants override the accent at runtime via `useBrand()`, so the
indigo here is the platform default rather than a hard-coded value.
