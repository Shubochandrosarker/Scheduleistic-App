# Scheduleistic — Security posture & hardening

This document records the security review of the platform and the hardening
applied in the Phase 5 pass. See [`01-architecture.md`](01-architecture.md) for
the system design these controls sit on top of.

## Threat model (multi-tenant SaaS)

- **Tenancy**: a Jetstream *team* = an Organization. Organizations own Workspaces;
  Workspaces own Channels/Posts/Comments/Approvals/TimeSlots/Feeds. A user may act
  on a workspace only if it belongs to their `currentTeam` **or** they're assigned
  via the `workspace_user` pivot (client portal). Guarded by
  `AuthorizesWorkspaceAccess` (`guardWorkspace`/`guardPost`).
- **Assets to protect**: OAuth access/refresh tokens, customer billing data, one
  tenant's content from another, the platform-admin control plane.

## Controls in place

| Area | Control |
|---|---|
| OAuth tokens | Encrypted at rest (`encrypted` cast) and `$hidden` on the `Channel` model |
| Cross-tenant data | `guardWorkspace`/`guardPost` on all workspace-scoped controllers; `PostComposer` re-scopes `channel_ids` to the workspace, so cross-workspace channel injection is impossible |
| Workspace member management | Owner/admin only (`guardManage`); external assignment is the intended client-portal behavior |
| Super-admin panel | `platform.admin` middleware; the `is_platform_admin` flag |
| CSRF | Laravel web middleware; OAuth `state` parameter validated on callback |
| Sessions | Encrypted/signed cookies; `AuthenticateSession` enabled |

## Hardening applied in Phase 5

1. **Privilege-escalation defense** — `is_platform_admin` removed from
   `User::$fillable`. It can no longer be set via mass assignment
   (`User::create`/`update($request->all())`); only seeders/console set it.
   Tests use a dedicated `platformAdmin()` factory state (`forceFill`).
2. **SSRF protection** — `App\Support\SsrfGuard` rejects non-HTTP(S) URLs and any
   host resolving to a private/loopback/link-local/reserved range (e.g. cloud
   metadata `169.254.169.254`, `localhost`, RFC1918). Enforced at feed creation
   (`FeedController`) and again at fetch time with DNS resolution
   (`RssIngestService`), plus a 10s fetch timeout.
3. **Organization suspension enforcement** — `EnsureOrganizationActive`
   (`org.active`) blocks suspended organizations from the feature routes while
   leaving **billing** reachable so owners can reactivate. Platform admins are
   never blocked.
4. **AI cost-abuse mitigation** — `/ai/generate` is throttled (20/min) and now
   requires an organization context.
5. **Impersonation hardening** — nested impersonation is rejected, and every
   impersonation is written to the audit log (admin id, org id, owner id).

## Brain Gateway integration (optional, disabled by default)

The AI assistant and post AI agents can optionally call out to the external
`wpistic-ai-gateway` (`BrainGatewayClient`) to ground generations in a team's
own brand knowledge and report usage. Requests are HMAC-signed with
`BRAIN_GATEWAY_SECRET`. It is off by default (`BRAIN_GATEWAY_ENABLED=false`),
fails soft if unreachable or misconfigured (AI features keep working
ungrounded), and `BRAIN_GATEWAY_FAKE=true` gives a deterministic local stub
with no outbound network calls for development.

## Audit findings reviewed and dismissed (false positives)

- *"ChannelController / PostController missing client-portal pivot check"* — these
  use an org-only check, which is **stricter** than the trait (no escalation).
  Connecting/removing social accounts is intentionally org-level.
- *"PostController doesn't validate channel ownership"* — `PostComposer` already
  filters channels by `workspace_id`.
- *"OAuth callback session can be tampered"* — session is server-side and signed;
  the workspace must pass `connect()` authorization before it can enter the
  session, and `callback()` re-authorizes.

## Known follow-ups for production (operational, not code)

- Real OAuth app credentials + Stripe keys/webhook secret in `.env`.
- GDPR data export/delete endpoints (if operating in the EU).
- Rate limiting at the edge (WAF) in addition to app-level throttles.

TLS automation for tenant custom domains shipped in Phase 6 (Caddy on-demand TLS gated by
`/tls/check` — see [`01-architecture.md`](01-architecture.md)#8.3) and is no longer a follow-up.
