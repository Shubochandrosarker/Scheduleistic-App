# Scheduleistic — Marketing Site

A production-grade, lightweight, fully static marketing website for **Scheduleistic**, a social media scheduling platform for agencies, creators, and small businesses.

This site is intentionally simple: pure semantic HTML5, one hand-written CSS file, and a tiny amount of vanilla JavaScript. There is **no build step, no framework, and no external libraries** — it deploys as plain static files.

> Note: This `marketing/` directory is completely separate from the Laravel SaaS app under `../scheduler/`. Nothing here touches the application.

## What's included

| File | Purpose |
| --- | --- |
| `index.html` | Home page (hero, problem, solution, how-it-works, features, agency workflow, channels, approvals, dashboard mock, pricing preview, FAQ, CTA, AEO answers) |
| `features.html` | Full feature breakdown |
| `pricing.html` | Plans: Free Trial, Pro, Agency, Scale |
| `agencies.html` | Agency-focused landing page |
| `client-approval.html` | Client approval workflow page |
| `scheduler.html` | Targets the keyword "social media scheduler" |
| `resources.html` | Blog/resources placeholder ("coming soon" + placeholder article cards) |
| `contact.html` | Accessible contact form + mailto fallback |
| `privacy.html` | Generic, complete SaaS Privacy Policy |
| `terms.html` | Generic, complete SaaS Terms of Service |
| `404.html` | Not-found page |
| `assets/css/styles.css` | The single stylesheet (CSS variables + all components) |
| `assets/js/main.js` | Tiny vanilla JS: mobile nav, FAQ accordion, footer year (deferred, < 2KB) |
| `favicon.svg` | SVG favicon (the actual `<link rel="icon">`) |
| `assets/og-image.svg` | SVG social/OG image source |
| `robots.txt` | Allows all crawlers, references the sitemap |
| `sitemap.xml` | Lists all real pages |

## Preview locally

From inside this `marketing/` directory:

```bash
python3 -m http.server 8000
```

Then open <http://localhost:8000/>.

Root-relative links (e.g. `/pricing.html`, `/assets/css/styles.css`) resolve correctly because the server's root is this directory. If you open the `.html` files directly with `file://`, the root-relative paths will not resolve — always use a local server.

## Deploy as static files

Upload the entire contents of this `marketing/` directory to any static host (Netlify, Vercel, Cloudflare Pages, GitHub Pages, S3 + CloudFront, Nginx, etc.). No server-side runtime is required.

- Set `404.html` as the custom not-found page in your host's settings if supported.
- `robots.txt` and `sitemap.xml` should be served from the site root.
- The canonical domain throughout is `https://scheduleistic.com`.

## Where to change the app domain / form endpoint

- **App domain (login/register/CTA links):** search for `app.scheduleistic.com` across the `.html` files and replace with your app host. CTA links point to `https://app.scheduleistic.com/register` and `https://app.scheduleistic.com/login`.
- **Marketing domain (canonical/OG/sitemap):** search for `scheduleistic.com` to change canonical URLs, Open Graph URLs, and the sitemap base.
- **Contact form endpoint:** in `contact.html`, the `<form action="https://app.scheduleistic.com/contact" method="post">` is a configurable placeholder. Point `action` at your own handler (serverless function, app route, or a form service). A `mailto:hello@scheduleistic.com` fallback is also provided.

## OG image (PNG)

Meta tags reference `https://scheduleistic.com/assets/og-image.png`, but the source shipped here is `assets/og-image.svg` (no external images are fetched). To generate the PNG that social platforms expect (1200×630), convert the SVG, for example:

```bash
# with rsvg-convert
rsvg-convert -w 1200 -h 630 assets/og-image.svg -o assets/og-image.png

# or with ImageMagick
convert -background none -resize 1200x630 assets/og-image.svg assets/og-image.png
```

Place the result at `assets/og-image.png`. The favicon uses `favicon.svg` directly and needs no conversion.

## SEO / AEO / GEO

- Unique `<title>` and meta description per page.
- Canonical URL, Open Graph, and Twitter card tags on every page.
- JSON-LD: `Organization` on all pages, `SoftwareApplication` (with pricing offers) on home + pricing, `FAQPage` on home + client-approval, `BreadcrumbList` on interior pages.
- Quotable AEO/GEO Q&A blocks on home and relevant pages.

## Core Web Vitals notes

- **No render-blocking JS:** `main.js` is loaded with `defer` and is tiny (< 2KB).
- **One external CSS file**, no web fonts — a **system font stack** avoids any font network request and eliminates layout shift from font swaps (low CLS).
- **No external images, no third-party scripts, no CDNs.** The "dashboard preview" is built entirely with CSS/divs, so there are no image downloads on the critical path.
- **Inline SVG** for icons, logo, favicon, and OG source — no icon-font or image requests.
- **Responsive, mobile-first CSS** with `clamp()` typography; respects `prefers-reduced-motion`.
- Fast LCP: the hero is plain text + system fonts, so the largest paint is text rendered immediately after CSS loads.

## Accessibility

- Semantic landmarks (`header`, `nav`, `main`, `footer`), one `<h1>` per page, logical heading order.
- Skip-to-content link, visible focus styles, ARIA labels on the nav toggle and FAQ accordions, `aria-current` on the active nav item, alt/`aria-hidden` handling on decorative SVGs.
- Color choices follow the brand dark theme with high-contrast text.
