# Using Scheduleistic

A walkthrough of the app itself — what you see, in what order, and what each screen actually
does. Written against the real UI, not the pitch. For installing or deploying the app, see
[`INSTALL.md`](INSTALL.md) and [`scheduler/app/DEPLOYMENT_HOSTINGER.md`](scheduler/app/DEPLOYMENT_HOSTINGER.md).

> Everything below works with **zero real social accounts or API keys** if the instance has
> `SOCIAL_FAKE=true` / `AI_FAKE=true` set (the default for local dev — see `INSTALL.md`).
> Channels connected this way behave like real ones in the UI, they just don't actually reach a
> real network.

---

## 1. The mental model

```
Your organization  (what you sign up as — an agency, or yourself)
   └── Workspaces  (one per client / brand)
          └── Channels  (that client's connected social accounts)
                 └── Posts you schedule, targeting one or more channels
```

Everything you do lives inside a **workspace**. If you're a solo creator, you'll likely have one
workspace for yourself. If you're an agency, create one workspace per client — their channels,
posts, and approvals never mix with another client's.

## 2. Signing up

1. Register with name/email/password at `/register`. Verify your email if the instance requires
   it.
2. Registering creates your **organization** (this is a Jetstream "team" under the hood — you'll
   see "Team Settings" in account menus, that's your organization).
3. You land on the **Dashboard**, showing your plan, usage against its limits, upcoming posts, and
   four quick actions: **Compose a post**, **Workspaces**, **Analytics**, **Billing**.

If you're running this yourself locally and want the super-admin view too, promote your own user
from the console (see `INSTALL.md` §2) — this isn't a button in the UI on purpose.

## 3. Create your first workspace

Go to **Workspaces**. Fill in a workspace name, optional client name, and timezone, then **Create**.
Each workspace in the list shows its client name, timezone, and how many channels it has, with a
**Manage channels** link and a **Delete** button (which also deletes everything under it — its
channels, posts, and comments).

Your plan caps how many workspaces, channels, team members, and monthly posts you get — see
**Billing** for your current usage against those limits.

## 4. Connect social channels

From a workspace's **Manage channels** page:

- **OAuth networks** (LinkedIn, Facebook, Instagram, Google Business Profile, Pinterest, Threads,
  TikTok, YouTube) show a **Connect {Network}** button that starts the OAuth flow — or, if this
  instance hasn't been given developer credentials for that network yet, a disabled
  "**{Network} (not configured)**" pill instead.
- **Token networks** (Mastodon, Bluesky, Medium, WordPress) show a **Connect {Network}** button
  that opens an inline form for pasting an access token or app password — no developer app
  needed on your end.
- Connected channels are listed with their provider and status, each with a **Disconnect** button.

## 5. Compose and schedule a post

From the Dashboard's **Compose a post**, or **+ Compose** on the Posts list:

1. Pick the **workspace** and the **channels** to post to (checkboxes, one per connected channel).
2. Optionally use the **✨ AI assistant** box — type a topic and hit **Generate** to get a
   brand-voice draft caption for the networks you selected (requires a Pro plan or above; see
   §9). You can edit whatever it produces before saving.
3. Write or edit the **content**, with a live character count.
4. Choose one:
   - Leave **Schedule for** empty → saves as a **draft**.
   - Set a **Schedule for** date/time → schedules it for that exact moment.
   - Check **Add to queue** instead → it takes the next open slot from that workspace's posting
     schedule (configured via time slots — see §11).
5. Optionally set **Repeat** (Never / Daily / Weekly / Monthly) for a recurring post — the next
   occurrence is created automatically once the current one publishes.
6. The submit button reads **Schedule post** or **Save draft** depending on what you chose.

## 6. What happens after you schedule

Publishing is never instant/inline — a background worker picks up due posts every minute and
publishes to each connected channel independently. On the **Posts** list you'll see one of these
statuses per post: `draft`, `pending_approval`, `scheduled`, `publishing`, `published`,
`partially_failed` (some channels succeeded, some didn't), or `failed`. A partial failure on one
network never blocks the others.

## 7. Approvals (agency / client review)

If your plan includes client approval (Agency and Scale):

1. A draft's **Submit for approval** button moves it to `pending_approval`.
2. Whoever has approval rights on that workspace — an approver, admin, owner, or the workspace's
   client user — sees **Approve** / **Reject** buttons on the Posts list for anything pending.
3. Approving schedules it; rejecting sends it back to draft.

The approval service also supports a **"request changes"** decision with a comment (distinct from
a flat reject), but as shipped the Posts list only wires up Approve and Reject buttons — request-
changes is available to anyone integrating against the API but doesn't have its own button yet.
Comments with @mentions are likewise a real, tested backend feature (`POST /posts/{post}/comments`)
without a dedicated comment-thread panel in the current UI.

## 8. Analytics

**Analytics** shows organization-wide totals (impressions, engagement, etc., depending on what
each network reports) captured automatically once posts publish — metrics are fetched hourly, so
give it time after your first publish before expecting numbers.

## 9. Billing & plans

**Billing** shows your current plan, usage against its limits, and the other three plans
side-by-side:

| Plan | Price | Workspaces | Channels | Members | Posts/mo | Client approval | White-label | Analytics | AI |
|---|---|---|---|---|---|---|---|---|---|
| Free Trial | $0 | 1 | 3 | 1 | 30 | – | – | – | – |
| Pro | $19/mo | 3 | 10 | 3 | 300 | – | – | Basic | Captions + agents |
| Agency | $49/mo | 15 | 50 | 10 | 2,000 | ✅ | ✅ | Basic | Captions + agents |
| Scale | $99/mo | 50 | 150 | 50 | 10,000 | ✅ | ✅ | Advanced | Captions + agents |

**Upgrade to {Plan}** sends you to Stripe Checkout; once subscribed, **Manage subscription** opens
the Stripe billing portal (update card, view invoices, cancel). Only the organization owner can
manage billing.

## 10. AI features (Pro and above)

- **Caption generation** — the ✨ box in the composer (§5).
- **Post AI agents** — once a post exists, agents can **rewrite/clean up** the copy, **optimize
  hashtags**, and run a **quality check** before you schedule it.

Both are optionally "grounded" in your organization's own brand knowledge if this instance has the
external Brain Gateway configured (an operator-level setting, off by default) — you won't notice
a difference in the UI either way, generation just works, or works with better brand context.

## 11. White-label branding & custom domain (Agency and Scale)

Under **White-label settings**:

- **Dashboard branding** — app name, tagline, primary/secondary color, and a logo URL. Saved
  branding is applied across the whole dashboard for everyone in your organization.
- **Custom domain** — enter a domain you own, then add the **TXT** record shown (proves
  ownership) and a **CNAME** pointing at the platform's domain. Click **Verify now**, or wait —
  it's checked automatically every few minutes. Once verified, HTTPS is issued automatically and
  your dashboard is reachable on your own domain, fully branded, with no further setup.

## 12. Team & roles

Invite teammates from your organization's **Team Settings** (standard Jetstream invite flow: an
email invite, they accept and join). Once invited, roles are:

| Role | Scope |
|---|---|
| **Owner** | Everything, including billing. The person who registered. |
| **Administrator** | Everything except billing. |
| **Team Member** | Creates/schedules posts and connects channels in workspaces they're assigned to. |
| **Approver** | Reviews and approves/rejects posts; can't create them. |
| **Client** | Limited to exactly one workspace: review, approve, and view analytics for that workspace only. Perfect for giving an actual client login access without exposing anything else. |

Workspace-level assignment (which workspaces a Team Member, Approver, or Client can actually see)
is managed per-workspace, separately from the organization-wide role above.

## 13. Platform admin (operators only)

If your user has been marked a platform admin (a console-only flag — see `INSTALL.md`), you get an
**Admin** panel listing every organization on the instance: owner, plan, workspace count, and
subscription status, with **Suspend/Unsuspend** and **Impersonate** (for support) on each. This is
for whoever operates the platform, not for regular customers.

## 14. Power-user features without a dedicated screen (yet)

These are real, tested backend capabilities exposed as routes but without their own settings page
in the dashboard today — useful to know about, driven via a direct form post or API call rather
than a button:

- **Bulk CSV import** — `POST /workspaces/{workspace}/import` with `content,scheduled_at,providers`
  rows creates many posts at once.
- **RSS / WordPress feeds** — `POST /workspaces/{workspace}/feeds` registers a feed URL; a
  background job polls it and auto-drafts a post per new article.
- **Time-slot queue templates** — `POST /workspaces/{workspace}/time-slots` defines the
  day/time slots that "Add to queue" (§5) fills in order.

## 15. Tips

- Not sure why a button is disabled or missing? Check your **plan** (§9) — most of the agency
  features (client approval, white-label, better analytics) are plan-gated.
- Nothing publishing? Make sure a queue worker is actually running — see `INSTALL.md`
  troubleshooting.
- Demoing without real accounts? Set `SOCIAL_FAKE=true` and `AI_FAKE=true` — every screen above
  works exactly the same, it just doesn't call a real network or LLM.
