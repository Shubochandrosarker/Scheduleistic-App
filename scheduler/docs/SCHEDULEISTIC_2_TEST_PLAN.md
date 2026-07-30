# Scheduleistic 2.0 — Test Plan and Results

## 1. Results

Run from `scheduler/app` with `php artisan test`.

| | Before 2.0 | After 2.0 |
| --- | --- | --- |
| Tests | 169 | **287** |
| Passing | 162 | **280** |
| Failing | 0 | **0** |
| Skipped | 7 | 7 |
| Assertions | 480 | **1,000** |
| Duration | ~8.4 s | ~7.7 s |

The 7 skipped tests are unchanged: Jetstream/Fortify feature-flag guards
(`markTestSkipped('… is not enabled.')`) in the API-token, registration,
email-verification, password-reset and account-deletion suites. They skip when
the corresponding feature is disabled in config. Not failures, not regressions.

**118 tests were added.** No existing test was deleted. One existing assertion
changed: `PlanLimitsTest::test_higher_plans_grant_larger_limits` moved the
Agency workspace limit from 15 to 20 to match the new plan ladder — the limit
went **up**, so no customer loses an entitlement.

---

## 2. Suites added

### Unit — `tests/Unit/PlanCapabilityTest.php` (12)

Entitlement resolution, tested directly rather than only through HTTP.

- Every plan declares every capability key (a capability added to one plan and
  forgotten in another fails here rather than defaulting to "on").
- Free grants no paid capabilities; capabilities increase with tier.
- Enterprise grants everything and reports unlimited limits.
- An unknown plan key falls back to free.
- An override **grants** a capability the plan lacks…
- …and **cannot revoke** one the plan grants.
- An override **raises** a limit but never lowers one.
- Unlimited (-1) beats any finite override.
- Legacy feature names still resolve; the legacy `analytics` tier is derived
  from capabilities.
- `upgradePlanFor()` names the cheapest plan that grants a capability.

### Unit — `tests/Unit/MediaValidatorTest.php` (14)

- A valid image post passes.
- Instagram rejects text-only; TikTok rejects images.
- Mixed media rejected where forbidden, allowed for Instagram carousels.
- Caption over the network limit is an error.
- Unsupported MIME rejected.
- Oversized file rejected, with the network's limit named in the message.
- Undersized image rejected; over-long video rejected.
- Out-of-range aspect ratio **warns** rather than blocking, and the warning
  carries an actionable crop suggestion.
- An unknown provider falls back to conservative defaults without throwing.
- Google Business explains why hashtags are unavailable.

### Feature — `tests/Feature/TenantIsolationTest.php` (15)

The suite that matters most, because tenancy failures are silent.

Reads: planner, media library, campaigns, ideas and channel health each return
only the caller's own records.
Filters: filtering by another tenant's campaign or workspace is a **validation
error**, not an empty result.
Writes: creating in another tenant's workspace, referencing another tenant's
campaign, updating/deleting another tenant's campaign, converting their idea,
reading/updating/deleting their asset, opening/rescheduling/bulk-editing their
post, deleting their pillar or tag — all refused.
Client portal: an assigned client sees only their assigned workspace.

### Feature — `tests/Feature/PlannerTest.php` (21)

Views: renders with filter options; each view derives its own (smaller) date
window; an invalid view is rejected; drafts with no date are always included.
Filters: campaign, pillar, tag and status; caption search treats `%` literally;
filters are echoed back for URL round-tripping.
Drawer: returns detail plus the viewer's permissions.
Rescheduling: drag to a new date; clearing the date returns a scheduled post to
draft; scheduling a draft promotes it; a publishing or published post cannot be
moved.
Bulk: absolute reschedule; **relative shift preserving the gap between posts**;
delete; submit → approve; tag and campaign assignment; a batch containing one
foreign id fails wholesale; an unknown action is rejected; the action is
audit-logged.

### Feature — `tests/Feature/MediaLibraryTest.php` (12)

Upload stored and **queued** (the request never does the expensive work);
identical bytes deduplicate; SVG rejected; an executable disguised as a JPEG
rejected (using a real `UploadedFile`, since Laravel's fakes report a mime type
from the filename); quota enforced before writing.
Processing probes dimensions, generates variants, leaves the original intact,
and is idempotent across repeated runs.
Status endpoint reports progress. Rename/favourite/archive. Deleting removes
every stored object. Quota accounting spans all workspaces in the account. The
library is gated below Pro.

### Feature — `tests/Feature/WebhookDeliveryTest.php` (17)

Management: secret shown exactly once, encrypted at rest, never serialised;
private and plain-HTTP URLs rejected; unknown events rejected; rotation
replaces the secret; gated below Agency; another account's endpoint is
untouchable.
Dispatch: one delivery per subscribed endpoint; workspace-scoped endpoints hear
only their own workspace; an unknown event throws.
Delivery: **signature verified exactly the way the docs tell receivers to
verify it**; 5xx throws so the queue retries and the failure streak increments;
an endpoint disables after the threshold; a success clears the streak; a
destination resolving to a private address is never called and disables the
endpoint; a delivered delivery is never re-sent; a replay creates a new
delivery with a fresh idempotency key.

### Feature — `tests/Feature/ChannelHealthTest.php` (11)

A channel starts connected; an expired token blocks publishing; a token
expiring soon warns but still publishes; a long-lived token is left alone
(this test caught a real bug — Carbon's signed `diffInDays` made a six-month
token read as expiring); repeated observations do not duplicate the event;
recovery resolves without deleting; the page summarises by severity; refresh
re-evaluates.
Engine integration: the publish job refuses a blocked channel; a failure is
recorded against the channel; a success stamps `last_published_at` and clears a
prior problem.

### Feature — `tests/Feature/ContentPlanningTest.php` (16)

New workspaces are seeded with default pillars, safely re-runnable. Campaigns:
UTM query built once at the model so attribution cannot fragment; end-before-
start rejected; deleting keeps posts; gated below Pro. Tags: case/whitespace
variants do not duplicate; the same name may exist in two workspaces. Ideas:
captured with tags; invalid stage rejected; conversion creates drafts, carries
the campaign across and advances the idea without consuming it; conversion is
capped. Notifications: documented defaults; preferences save; an unknown event
is rejected; the centre renders.

---

## 3. Coverage against the brief

| Required | Status |
| --- | --- |
| Unit: plan capabilities | ✅ 12 tests |
| Unit: usage limits | ✅ existing `PlanLimitsTest` (6) |
| Unit: provider validation | ✅ 14 tests |
| Unit: channel health state | ✅ within `ChannelHealthTest` |
| Feature: tenant isolation | ✅ 15 tests |
| Feature: calendar movement | ✅ within `PlannerTest` |
| Feature: bulk scheduling | ✅ within `PlannerTest` |
| Feature: media upload | ✅ 12 tests |
| Feature: plan upgrade/downgrade | ✅ capability gates + override tests |
| Feature: white-label domain access | ✅ existing `CustomDomainTest`, `WhiteLabelTest` |
| Queue: publish job | ✅ existing `PublishingEngineTest` (5), extended by health tests |
| Queue: retry behaviour | ✅ within `WebhookDeliveryTest`; publish retries in `PublishingEngineTest` |
| Queue: partial failures | ✅ existing `PublishingEngineTest` |
| Queue: media processing | ✅ within `MediaLibraryTest` |
| Queue: webhook retry | ✅ within `WebhookDeliveryTest` |
| Unit: best-time calculations | ❌ feature not implemented |
| Unit: UTM generation | ✅ within `ContentPlanningTest` |
| Unit: report metrics | ❌ feature not implemented |
| Unit: AI usage accounting | ❌ feature not implemented |
| Unit: link attribution | ❌ feature not implemented |
| Feature: per-network variants (composer UI) | ⚠️ data model + validation tested; the three-panel composer is not built |
| Feature: approval stages (multi-level) | ❌ feature not implemented |
| Feature: guest approval | ❌ feature not implemented |
| Feature: inbox assignment | ❌ feature not implemented |
| Feature: report generation | ❌ feature not implemented |
| Feature: OAuth reconnect | ⚠️ existing `ChannelConnectTest` covers connect/callback/state; a dedicated reconnect flow is not built |
| Frontend tests (Vitest) | ❌ no JS test runner is configured in this repository |
| End-to-end tests | ❌ no Playwright/Dusk harness is configured |

Items marked ❌ correspond to features not implemented in this release — see
`SCHEDULEISTIC_2_RELEASE_NOTES.md`. They are listed rather than omitted so the
gap is visible.

---

## 4. Running the suite

```bash
cd scheduler/app
composer install                 # add --ignore-platform-req=ext-bcmath if bcmath is missing
npm install
cp .env.example .env
php artisan key:generate         # required — the suite 500s without it
npm run build                    # required — Inertia pages need the Vite manifest
php artisan test
```

`php artisan key:generate` and `npm run build` are genuinely prerequisites, not
optional: without them every HTTP test fails with "No application encryption
key" and "Vite manifest not found" respectively. This was not documented before
2.0.

Filter a single suite:

```bash
php artisan test --filter=TenantIsolationTest
```

---

## 5. Manual verification checklist

Automated coverage does not extend to the browser, so before a release:

1. `SOCIAL_FAKE=true` end-to-end: register → create brand → connect a fake
   profile → compose → schedule → run the queue → confirm published.
2. Planner at 320px width: month scrolls horizontally inside its container and
   the page body does not.
3. Keyboard: Tab reaches the skip link first; the post drawer traps focus,
   Escape closes it, focus returns to the card.
4. Dark and light themes on every new page.
5. `prefers-reduced-motion: reduce` — skeleton shimmer stops.
6. Upload a 20 MB video and confirm the request returns before processing does.
