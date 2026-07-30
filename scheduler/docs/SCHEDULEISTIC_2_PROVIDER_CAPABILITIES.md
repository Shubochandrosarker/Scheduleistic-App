# Scheduleistic 2.0 — Network Integration Capabilities

What each network actually supports, where that lives, and — importantly —
what Scheduleistic does **not** claim to do.

Source of truth: `scheduler/app/config/provider_capabilities.php`.
Enforced by: `App\Services\MediaValidator`, read by
`App\Services\ProviderCapabilityService`.

Nothing in the Vue layer hard-codes a provider rule. The composer asks for
these definitions and renders whatever it is told, so adding a network is a
config block plus a driver class — no UI change.

---

## 1. How the definitions are used

Three times, on purpose:

1. **In the composer**, to hide or disable options a network cannot do, always
   with the reason attached (`notes`).
2. **Before scheduling**, so problems surface while the author is still there.
3. **Inside `PublishPostJob`**, immediately before the driver call. A post can
   be edited after it was scheduled, and a network's rules can change under
   it — the composer's check is advice, the job's is authoritative.

Errors block publishing. Warnings do not, and carry an actionable suggestion
("Crop to 1080×566 to reach 1.91:1") rather than "fix the aspect ratio".

An unknown provider falls back to conservative defaults instead of throwing, so
a driver added ahead of its config block still publishes safely.

---

## 2. Caption limits

| Network | Characters | Media required |
| --- | --- | --- |
| Instagram | 2,200 | Yes |
| Facebook | 63,206 | No |
| LinkedIn (personal + company) | 3,000 | No |
| Threads | 500 | No |
| Bluesky | 300 | No |
| Mastodon | 500 | No |
| TikTok | 2,200 | Yes (video) |
| YouTube | 5,000 | Yes (video) |
| Pinterest | 500 | Yes |
| Google Business Profile | 1,500 | No |
| Medium | 100,000 | No |
| WordPress | 100,000 | No |

## 3. Composer options

| Network | First comment | Alt text | Location | Threads | Thumbnail | Board | Privacy | Category |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Instagram | ✅ | ✅ | ✅ | — | — | — | — | — |
| Facebook | ✅ | ✅ | ✅ | — | — | — | — | — |
| LinkedIn | — | ✅ | — | — | — | — | — | — |
| Threads | — | ✅ | — | ✅ | — | — | — | — |
| Bluesky | — | ✅ | — | ✅ | — | — | — | — |
| Mastodon | — | ✅ | — | ✅ | — | — | — | — |
| TikTok | — | — | — | — | ✅ | — | ✅ | — |
| YouTube | — | — | — | — | ✅ | — | ✅ | ✅ |
| Pinterest | — | ✅ | — | — | — | ✅ | — | — |
| Google Business | — | — | ✅ | — | — | — | — | — |
| WordPress | — | — | — | — | — | — | — | ✅ |

## 4. Media rules

| Network | Max images | Max video | Mixed | Image size | Video length |
| --- | --- | --- | --- | --- | --- |
| Instagram | 10 | 1 | ✅ | ≤100 MB, 0.8–1.91 ratio | ≤90 s |
| Facebook | 10 | 1 | — | ≤10 MB | ≤4 h |
| LinkedIn | 9 | 1 | — | ≤10 MB | ≤10 min |
| Threads | 20 | 1 | — | ≤8 MB | ≤5 min |
| Bluesky | 4 | 1 | — | **≤1 MB** | — |
| Mastodon | 4 | 1 | — | ≤16 MB (GIF ok) | — |
| TikTok | 0 | 1 | — | — | 3 s–10 min, ≤500 MB |
| YouTube | 0 | 1 | — | — | ≤12 h, ≤2 GB |
| Pinterest | 1 | 1 | — | ≤20 MB, 0.5–1.0 ratio | ≤15 min |
| Google Business | 1 | 0 | — | ≥250×250, ≤5 MB | — |

---

## 5. Documented limitations

These are surfaced in the UI as explanations, not hidden:

- **Instagram** does not make caption links clickable. Use a link-in-bio page.
- **Instagram** will not accept a text-only post.
- **TikTok** publishing through the API is video-only, and captions do not
  render clickable links.
- **Bluesky** compresses aggressively and rejects images over 1 MB.
- **Pinterest** requires a board on every Pin, and favours 2:3 vertical images.
- **Google Business Profile** has no @mention concept and does not index
  hashtags.
- **YouTube** requires a title of 100 characters or fewer.

---

## 6. Provider limitations beyond media

Honest statements about what the platform does **not** currently do, so nobody
plans around a feature that is not there:

- **Analytics coverage varies by network and is not normalised yet.** The 1.x
  `AnalyticsService` returns organization-lifetime totals; the 2.0 analytics
  workspace (date ranges, comparisons, per-campaign breakdowns) is not
  implemented in this release.
- **No social inbox.** Comment, mention and DM synchronisation is not
  implemented. Several networks (Bluesky, Mastodon, Medium, WordPress) have no
  usable DM surface at all, and Instagram/Facebook messaging requires
  additional app review most tenants will not have.
- **Competitor tracking is not implemented.** When it is, it will use official
  APIs or compliant data providers only — the platform does not and will not
  scrape networks in violation of their terms, and any public-only figures will
  be labelled as estimates.
- **Threaded posts** are declared as a capability for Threads, Bluesky and
  Mastodon but the composer does not yet author them.
- **Video probing is metadata-only.** Duration and codec are validated when the
  uploader supplies them; the server does not run ffprobe, so a video uploaded
  without metadata is checked on size and type only.

---

## 7. Adding a network

1. Write the driver implementing `App\Social\Contracts\SocialProvider`.
2. Register it in `App\Social\ProviderManager::$drivers`.
3. Add a block to `config/provider_capabilities.php` — including `notes` for
   anything it cannot do.
4. Add presentation metadata to `resources/js/networks.js`.
5. Add it to `config/scheduleistic.php` `providers` if it should appear in the
   connect UI.

No composer, planner or validator change is required.
