# Scheduleistic — Marketing Site

A production-grade, lightweight, fully static marketing website for **Scheduleistic**, a social media scheduling platform for agencies, creators, and small businesses.

This site is intentionally simple: pure semantic HTML5, one hand-written CSS file, and a tiny amount of vanilla JavaScript. There is **no build step, no framework, and no external libraries** — it deploys as plain static files.

## Design system & premium positioning

The brand is a dark forest-green/mint palette (`--bg`, `--surface`, `--card`, `--primary`,
`--cta` in `assets/css/styles.css`) with **zero web fonts** (a deliberate performance choice — a
system-font stack avoids any font network request or layout shift). On top of that base, the site
uses a few deliberate "premium SaaS" touches, all pure CSS/vanilla JS with no new dependencies:

- **Ambient hero glow** — soft layered radial-gradients behind the hero and reused wherever a
  `.hero` section appears (every page that uses one inherits it for free).
- **Gradient CTA buttons** (`.btn-cta`) with a soft glow shadow that intensifies on hover, instead
  of a single flat fill.
- **Gradient text** (`.grad-text`) for the one accent phrase in the homepage headline.
- **Card lift + glow on hover** (`.card`) and a refined "browser chrome" mock panel style
  (`.mock`) — a top accent gradient bar and a deeper, layered shadow instead of a flat card.
- **Scroll reveal** (`.reveal` + `main.js`) — sections fade/slide in as they enter the viewport.
  **Content is visible by default and JS is the only thing that ever hides it** (inline styles
  applied at runtime, not CSS that hides by default) — if the script is blocked, slow, or errors
  out, nothing on the page is ever invisible. There's also a 4-second force-reveal fallback and a
  `prefers-reduced-motion` check that skips the whole thing.
- **Hero fact strip** — real, verifiable facts (13 native networks, isolated client workspaces,
  white-label custom domains, self-hosted-or-managed) instead of invented metrics or testimonials.
  `agencies.html` is explicit about this: "no invented metrics here" — customer-story placeholders
  say "coming soon" rather than faking numbers.

The positioning goal throughout: read as an **agency-grade operations platform**, not a bare
scheduling tool — the hero copy, the fact strip, and the features pages all lead with tenancy
isolation, approval workflows, and white-label rather than "schedule your posts."

## Header & footer navigation

The header nav is grouped, not flat, to stay clean at any width:

- **Product** is a dropdown (`.nav-item-dropdown` / `.nav-dropdown-toggle` / `.nav-dropdown-menu`
  in `main.js`) containing Features, Social Media Scheduler, and Client Approval — three pages
  that were previously three separate top-level links.
- Top-level items: **Product ▾**, Agencies, Pricing, Resources, Contact, Login, plus a single
  **Start Free Trial** button (the redundant second "View Pricing" nav button was removed —
  Pricing is already a nav link).
- The dropdown is click-to-open (not hover-only, so it works on touch), closes on an outside
  click or <kbd>Escape</kbd>, and uses `aria-haspopup`/`aria-expanded`/`role="menu"` for
  accessibility.
- **On mobile** (≤760px) the exact same markup becomes an inline accordion instead of a floating
  panel — tapping **Product** expands its three links in place, indented, within the full-width
  mobile menu. No separate mobile-only markup; one CSS media query changes the presentation.
- The current page is highlighted in the nav — a flat link gets `aria-current="page"`; if the
  current page lives inside the Product dropdown, the **Product** toggle itself gets an
  `is-current` style so you can tell which section you're in even while the dropdown is closed.

The footer was regrouped for the same reason: **Company** used to mix real company pages with an
unrelated account action (Login sat in a list with "For Agencies"/"Resources"/"Contact"). Login
now lives in the footer's bottom utility row next to **Start Free Trial**, and **Company** is
three actually-related pages.

Since this site has no templating/include system, the header and footer markup is duplicated
across all 11 HTML pages — when you change the nav or footer, update every page, not just one.
There's no build step to regenerate them from a shared partial; a future change here could add
one (e.g. a small script that stamps a shared header/footer template into each page at deploy
time), but today it's a manual, page-by-page edit.

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
| `assets/js/main.js` | Vanilla JS: mobile nav, nav dropdown/accordion, FAQ accordion, scroll reveal, footer year (deferred) |
| `favicon.svg` | SVG favicon (the actual `<link rel="icon">`) |
| `assets/og-image.svg` | SVG social/OG image source (edit this one) |
| `assets/og-image.png` | Pre-rendered 1200×630 PNG used by the `og:image`/`twitter:image` meta tags |
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

Meta tags reference `https://scheduleistic.com/assets/og-image.png`. `assets/og-image.svg` is the
editable source; `assets/og-image.png` (1200×630) is the pre-rendered copy checked into the repo,
so social platforms that don't render SVG (most of them) get a real preview image out of the box.
If you edit the SVG, regenerate the PNG the same way, for example:

```bash
# with rsvg-convert
rsvg-convert -w 1200 -h 630 assets/og-image.svg -o assets/og-image.png

# or with ImageMagick
convert -background none -resize 1200x630 assets/og-image.svg assets/og-image.png

# or with Python (cairosvg)
python3 -c "import cairosvg; cairosvg.svg2png(url='assets/og-image.svg', write_to='assets/og-image.png', output_width=1200, output_height=630)"
```

The favicon uses `favicon.svg` directly and needs no conversion.

## SEO / AEO / GEO

- Unique `<title>` and meta description per page.
- Canonical URL, Open Graph, and Twitter card tags on every page.
- JSON-LD: `Organization` on all pages, `SoftwareApplication` (with pricing offers) on home + pricing, `FAQPage` on home + client-approval, `BreadcrumbList` on interior pages.
- Quotable AEO/GEO Q&A blocks on home and relevant pages.

## Core Web Vitals notes

- **No render-blocking JS:** `main.js` is loaded with `defer` and stays small (a few KB, no dependencies) — nav, dropdown, FAQ, and scroll-reveal logic, nothing else.
- **One external CSS file**, no web fonts — a **system font stack** avoids any font network request and eliminates layout shift from font swaps (low CLS).
- **No external images, no third-party scripts, no CDNs.** The "dashboard preview" is built entirely with CSS/divs, so there are no image downloads on the critical path.
- **Inline SVG** for icons, logo, favicon, and OG source — no icon-font or image requests.
- **Responsive, mobile-first CSS** with `clamp()` typography; respects `prefers-reduced-motion`.
- Fast LCP: the hero is plain text + system fonts, so the largest paint is text rendered immediately after CSS loads.

## Accessibility

- Semantic landmarks (`header`, `nav`, `main`, `footer`), one `<h1>` per page, logical heading order.
- Skip-to-content link, visible focus styles, ARIA labels on the nav toggle and FAQ accordions, `aria-current` on the active nav item, alt/`aria-hidden` handling on decorative SVGs.
- Color choices follow the brand dark theme with high-contrast text.
