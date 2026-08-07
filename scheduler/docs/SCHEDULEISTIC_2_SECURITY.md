# Scheduleistic 2.0 — Security

What is enforced, where it is enforced, and what is **not** yet covered.

---

## 1. Tenant isolation

The single most important property of a multi-tenant scheduler, and the one
whose failures are silent.

- **One definition of the boundary.** `User::visibleWorkspaceIds()` — the
  workspaces owned by the user's current organization plus any they are
  individually assigned to. Every controller, policy and form request resolves
  through it. Before 2.0 each controller re-derived it, which is how a new
  controller ends up with no guard.
- **IDs from the client are never trusted.** `ScopesToWorkspace` validates
  `workspace_id` with `Rule::in($user->visibleWorkspaceIds())`, and every
  related id (campaign, pillar, tag, asset, assignee) must belong to the same
  workspace. A foreign id produces a validation error, not an empty result — an
  empty result would still confirm the id exists.
- **Policies, not ad-hoc checks.** `WorkspaceScopedPolicy` asks tenancy first
  (absolute, no permission overrides it), then role permission. Subclasses name
  permissions; they never re-implement tenancy.
- **Assignees are checked too.** Assigning a post to someone without workspace
  access is refused, because the resulting notification would leak content.
- **Bulk actions fail wholesale.** One foreign id in a batch of 200 rejects the
  entire request. A half-applied bulk edit is worse than a refused one.

**Tested by** `tests/Feature/TenantIsolationTest.php` (15 tests) covering
reads, writes, filters, cross-tenant references and the client portal.

---

## 2. Authentication and session

Inherited from Jetstream/Fortify and unchanged: two-factor authentication,
password confirmation for sensitive actions, browser-session invalidation,
email verification, rate-limited login.

**Impersonation** requires password confirmation to start, shows a permanent
banner, and is audit-logged: start, stop, and timeout-expiry each write an
append-only `audit_logs` row, the session ID is regenerated on start and
stop, a demoted/deleted admin cannot be restored on stop, and a session
force-ends after an hour if nobody clicks "Stop impersonating." The stop
action is available to the impersonated session. This covers the
impersonation lifecycle itself, not every individual write made during the
session — the banner is worded to match.

On top of the lifecycle controls, a fixed set of high-risk actions is
blocked outright for the duration of an impersonated session: billing
checkout/portal, password/profile/2FA/passkey changes, revoking other
devices' sessions, and deleting the account, the organization, or a
workspace. Ordinary content management (campaigns, ideas, media, channels)
stays available — blocking that too would defeat the point of impersonating
someone to fix their account. A break-glass escape hatch exists for the
tenant-explicitly-asked-for-it case: supplying `break_glass_reason` lets the
action through, but writes its own `impersonation.break_glass` audit row
(admin, impersonated user, route, and reason), so the override is
exceptional and traceable rather than silent.

---

## 3. Authorization

| Layer | Mechanism |
| --- | --- |
| Route | `auth:sanctum` + `verified` + `org.active` |
| Entitlement | `capability:<key>` middleware → 402 with the unlocking plan |
| Record | Policy (`WorkspaceScopedPolicy` subclasses) |
| Field | Form Request rules, incl. cross-workspace reference checks |

Hiding a navigation item is presentation. Every capability-gated route carries
the middleware, so a hand-crafted request cannot reach an unpaid feature.
`MediaLibraryTest` and `WebhookDeliveryTest` assert the gate directly.

Webhook management is further restricted to the organization owner and
administrators.

---

## 4. Credentials

- OAuth tokens: `encrypted` cast on `Channel::access_token` / `refresh_token`,
  plus `$hidden` so they never reach an Inertia prop or JSON response.
  Asserted by `WorkspaceTest::test_channel_oauth_tokens_are_encrypted_at_rest`.
- Webhook signing secrets: `encrypted` cast, `$hidden`, shown in plaintext
  **exactly once** (creation and rotation) via a one-shot flash. There is no
  endpoint that reveals a secret again.
  Asserted by `WebhookDeliveryTest::test_creating_an_endpoint_shows_the_secret_exactly_once`.
- OAuth `state` is generated and verified on callback.

---

## 5. SSRF

`App\Support\SsrfGuard` rejects non-HTTP(S) schemes, `localhost`,
`*.local`, `*.internal`, cloud metadata hosts, and any host resolving to a
private, loopback, link-local or reserved address.

Applied to:

- RSS / WordPress feed ingestion (1.x).
- **Webhook destinations at creation** — a private URL fails validation.
- **Webhook destinations on every delivery attempt** — DNS can be re-pointed
  after the endpoint is saved, and the worker sits inside the network
  perimeter. A destination that resolves badly at send time is never called and
  the endpoint is disabled.
  Asserted by
  `WebhookDeliveryTest::test_a_destination_that_resolves_to_a_private_address_is_never_called`.

---

## 6. File upload

Three independent defences, because any one alone is bypassable:

1. `mimetypes:` reads the file's **actual** content type — not the
   client-supplied `Content-Type`, not the extension.
2. An **allow-list**, not a deny-list:
   `image/jpeg|png|webp|gif`, `video/mp4|quicktime|webm`.
   **SVG is deliberately absent** — it is an executable document and there is
   no safe way to serve an untrusted one same-origin with the dashboard.
3. Plan **storage quota** checked before a byte is written, plus a hard
   per-upload ceiling (`MEDIA_MAX_UPLOAD_MB`).

Additionally:

- Stored filenames are derived from the guessed extension, never the client's
  filename. The original name is sanitised and kept for display only.
- An **antivirus hook** (`MEDIA_AV_COMMAND`) runs against the file before it is
  marked ready; a non-zero exit quarantines the asset and deletes the object.
  Left unset, scanning is skipped — the hook exists so operators can wire in
  ClamAV without patching application code.
- Asset URLs are generated per request (signed on S3), so an object key is
  never exposed and cannot be guessed across tenants.

Asserted by `MediaLibraryTest` — including an executable disguised as a JPEG,
which is tested with a *real* `UploadedFile` because Laravel's fake files
report a mime type derived from the filename and would let the test pass
without proving anything.

---

## 7. Injection

- **SQL** — Eloquent bindings throughout. Two raw fragments exist, both
  parameterised: the LIKE searches in `PlannerQuery` and `MediaAssetController`,
  which use `whereRaw('… LIKE ? ESCAPE ?')`.
- **LIKE wildcards** — user input is escaped with `addcslashes($s, '%_\\')`
  and an explicit `ESCAPE` clause. Without it, searching for `50%` would match
  every row on SQLite and Postgres (MySQL assumes a backslash escape; the
  others do not). Asserted by
  `PlannerTest::test_caption_search_matches_content_and_treats_wildcards_literally`.
- **XSS** — Vue escapes by default. The one `v-html` in the codebase renders
  Laravel's own paginator labels.
- **Mass assignment** — every model declares `$fillable`; state transitions
  that must not be user-settable (`status`, `assignee_id` in bulk,
  `consecutive_failures`, `health_state`) are written with `forceFill()` from
  validated input rather than passed through `update()` of request data.

---

## 8. Idempotency

| Operation | Mechanism |
| --- | --- |
| Publish | `PublishPostJob` returns early if the target is already `published` |
| Media upload | Unique `(workspace_id, checksum)` — identical bytes return the existing asset |
| Media processing | Recomputes from the stored original and overwrites `variants`; a retry produces the same result |
| Webhook delivery | `event_id` UUID sent as `X-Scheduleistic-Delivery`; a delivered row is never re-sent |
| Stripe webhooks | Cashier's own handling (unchanged) |

---

## 9. Audit logging

`audit_logs` is append-only — `AuditLog::record()` is the only writer, there is
no `updated_at`, and nothing in the codebase updates or deletes a row.

Currently recorded: campaign create/update/delete, idea conversion, bulk
planner actions, webhook create/update/delete/rotate.

`channel_health_events` follows the same discipline: recovery stamps
`resolved_at` rather than removing the record, so the timeline survives.

---

## 10. Rate limiting

- AI endpoints: `throttle:20,1`.
- Publishing: per-channel limiter driven by each driver's declared
  `rateLimit()`.
- Webhook delivery: bounded attempts with exponential backoff, plus automatic
  disable after 10 consecutive failures — a broken receiver cannot be used to
  make the queue hammer a third party indefinitely.

---

## 11. Not yet covered

Stated plainly rather than implied:

- **No CSV export exists yet**, so formula-injection escaping for exports is
  not implemented. When export ships, values beginning `= + - @ TAB CR` must be
  prefixed with `'`. CSV *import* (`BulkImporter`) is 1.x code and reads only.
- **Antivirus scanning is a hook, not a bundled scanner.** With
  `MEDIA_AV_COMMAND` unset, uploads are not scanned.
- **No guest approval links yet**, so link expiry and scoped guest tokens are
  not implemented.
- **No SSO/SAML.** Listed as an Enterprise capability flag; not built.
- **Video content is not deeply inspected** — no ffprobe, so codec and duration
  are validated only when supplied.
- **Browser push notifications** are a stored preference only; no service
  worker or push subscription exists yet.
- **Dependency advisories** outstanding at release: 4 medium
  (`guzzlehttp/guzzle`, transitive) and 2 high
  (`axios`, `postcss` — build-time). See the deployment doc.

---

## 12. Reporting a vulnerability

Do not open a public issue. Email the maintainer listed in the root README with
reproduction steps and affected versions.
