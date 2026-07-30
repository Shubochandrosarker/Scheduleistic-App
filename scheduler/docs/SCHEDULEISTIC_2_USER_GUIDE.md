# Scheduleistic 2.0 — User Guide

Covers what is in the product today. Anything not described here is not built
yet — see `SCHEDULEISTIC_2_RELEASE_NOTES.md`.

---

## Vocabulary

| You'll see | It means |
| --- | --- |
| **Account** | Your organization — the billing boundary |
| **Brand** (or **Client**) | One business you post for. Agencies see "Client", in-house teams see "Brand" |
| **Social profile** | A connected social account |
| **Destination** | One profile a post publishes to |

---

## Getting started

1. **Create a brand.** Manage → Brands → New. It is seeded with six content
   pillars (Education, Promotion, Engagement, Community, Product, Testimonial)
   so your calendar has something to categorise by immediately.
2. **Connect a social profile.** Open the brand → Connect. Tokens are encrypted
   and never shown back to you.
3. **Compose.** The **New post** button is in the top bar on desktop and is the
   raised centre button on mobile.
4. **Watch profile health.** Manage → Social profiles shows every connection's
   state, when its token expires and when it last published.

---

## The calendar

Plan → Calendar. Five views, all sharing one set of filters:

| View | Use it for |
| --- | --- |
| **Month** | The overview — six weeks at a glance |
| **Week** | Working a single week in detail |
| **Agenda** | A chronological list of the next fortnight |
| **List** | Scanning and bulk-selecting |
| **Grid preview** | Planning how an Instagram feed will look |

### Cards

Each card shows the time, status (as a **word**, not only a colour), the
networks it goes to, a stripe in the brand's colour, its campaign, and any
publishing error — you never have to open a post to find out it failed.

### Moving posts

**Drag a card onto another day.** The time of day is kept; only the date moves.

Posts that are currently publishing or already published cannot be dragged —
the system refuses rather than letting you move something out from under the
queue.

### Unscheduled drafts

Drafts with no date appear in a tray above the calendar. Drag one onto a day to
schedule it; drag a scheduled post's date away in the drawer to send it back.

### Filtering

Filter by brand, network, status, campaign, content pillar, tag, assignee, or
search captions. **Every filter goes into the URL**, so a filtered calendar is a
link you can send to a colleague or bookmark, and the back button behaves.

Click **Save view** to keep a filter combination. Saved views are stored in your
browser, so they do not follow you to another device.

### Bulk editing

Tick the checkbox on any card. The action bar appears with:

- **Push 1 day / Push 1 week** — moves every selected post by that amount,
  *preserving the gaps between them*.
- **Submit for approval**, **Approve**, **Delete**.

If any selected post is outside what you can edit, the whole action is refused
rather than applying to some of them.

### The post drawer

Clicking a card opens a drawer over the calendar rather than navigating away.
It shows the caption, schedule, assignee, campaign, pillar, every destination
and its outcome, per-network overrides, comments and the full approval history —
plus the actions you personally are allowed to take.

Press **Escape** to close it; focus returns to the card you opened.

---

## Campaigns

Plan → Campaigns *(Pro and above)*.

A campaign groups posts, ideas and reporting around one objective. Give it a
goal, dates, a budget, a target URL and UTM defaults; the UTM values are built
into a canonical query string once, so attribution never fragments across
tools.

**Deleting a campaign never deletes its posts.** They simply lose the label.

---

## Content pillars and tags

**Pillars** are what a post *is* — education, promotion, community. Every brand
starts with six and you can add your own. Each can carry a target share, so you
can see that you are 40% promotional against a 20% target.

**Tags** are free-form. Typing a tag that already exists reuses it rather than
creating a duplicate — case and stray spaces included. The same tag name can
exist in two brands without them colliding.

Both are available on every plan: they are how content gets organised at all.

---

## Ideas

Plan → Ideas *(Pro and above)*.

A pipeline for half-formed thoughts:

```
Inbox → Researching → Drafting → Ready → Scheduled → Published → Archived
```

Drag a card between columns on the board, or change its stage in the list view.
Give it a title, notes, a source URL, a priority, an assignee, a due date, a
campaign and a pillar.

**Turn an idea into posts.** "To draft" creates up to ten drafts at once,
carrying the campaign, pillar and assignee across. The idea is not consumed —
it moves to *Drafting* and keeps a link to what it produced.

---

## Media library

Create → Media library *(Pro and above)*.

Upload by dragging files onto the page or using the Upload button. Multiple
files upload one at a time so a large video does not starve the rest.

**What happens after upload.** The file is stored immediately and the page
returns; dimensions, thumbnails and optimised copies are generated in the
background. A card shows "Processing…" until that finishes. **Your original is
never modified** — everything the app renders is a separate derivative.

**Accepted:** JPEG, PNG, WebP, GIF, MP4, MOV, WebM.
**Not accepted:** SVG. It is an executable document, and serving an untrusted
one from the dashboard's own origin is not safe.

**Duplicates.** Uploading the same file twice returns the existing asset rather
than storing it again — so a flaky connection and a retried upload cost nothing.

**Alt text.** Click any asset to edit its alt text inline. Assets without alt
text are flagged. This is published as accessibility metadata on the networks
that support it.

**Internal notes** are for your team and are never published.

**Storage.** The bar at the top shows how much of your plan's allowance you have
used, across every brand in the account. An upload that would exceed it is
refused before anything is written.

---

## Notifications

The bell in the top bar, or Home → Notifications.

Under **Preferences** you control, per event, whether you get it in the app, by
email, and how often (immediately, daily digest, weekly digest, or off). The
fourteen events cover approvals, mentions, assignments, failed and partial
publishes, expiring connections, reports, billing, usage thresholds, domain
verification, imports and feed errors.

---

## Social profile health

Manage → Social profiles.

Three counts at the top: healthy, needs attention, cannot publish. Below, every
profile with:

- Its state, stated in words as well as colour
- When its token expires
- When it last published, last synced metrics and was last checked
- A **Reconnect** button when it can no longer publish

Scheduleistic re-checks hourly and emails you when a profile *changes* into a
bad state — not every hour it stays there. A profile with an expired token
fails fast with a readable reason instead of silently retrying.

---

## Webhooks

Account → Webhooks *(Agency and above)*.

Get a signed HTTPS POST when something happens. Create an endpoint, choose your
events, and **copy the signing secret — it is shown once and never again.**

Today `publish.succeeded` and `publish.failed` fire. Verify with:

```
sha256=hmac_sha256(secret, timestamp + "." + raw_body)
```

using the `X-Scheduleistic-Timestamp` and `X-Scheduleistic-Signature` headers.
De-duplicate on `X-Scheduleistic-Delivery`.

Failed deliveries retry with growing gaps and are listed with their response
codes. **Replay** re-sends one without re-running whatever produced it. An
endpoint that fails ten times in a row is disabled automatically and says so.

---

## Plans

| | Free | Solo | Pro | Agency | Scale |
| --- | --- | --- | --- | --- | --- |
| Brands | 1 | 1 | 5 | 20 | 50 |
| Social profiles | 3 | 10 | 25 | 75 | 200 |
| Team members | 1 | 1 | 5 | 15 | 50 |
| Posts / month | 30 | 500 | 2,000 | 10,000 | 50,000 |
| Storage | 200 MB | 2 GB | 10 GB | 50 GB | 250 GB |
| Media library | — | — | ✅ | ✅ | ✅ |
| Campaigns + ideas | — | — | ✅ | ✅ | ✅ |
| Approvals | — | — | ✅ | ✅ | ✅ |
| White-label + custom domain | — | — | — | ✅ | ✅ |
| Webhooks + API | — | — | — | ✅ | ✅ |

Enterprise is custom. A navigation item you are not entitled to still appears —
it tells you which plan unlocks it rather than quietly vanishing.

---

## Keyboard and accessibility

- **Tab** from the top of any page reaches "Skip to main content" first.
- The post drawer traps focus while open; **Escape** closes it and focus
  returns to the card.
- Every status is a word as well as a colour.
- Icon-only buttons carry accessible names.
- Touch targets are at least 44px.
- If your system asks for reduced motion, animation is switched off.

---

## Mobile

The bottom bar carries Home, Calendar, **Compose** (the raised centre button),
Alerts and More. The calendar's agenda and list views are the most usable on a
phone; month and week scroll horizontally inside their own container so the
page itself never scrolls sideways.
