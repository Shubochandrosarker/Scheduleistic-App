# Wpistic Content Automation — Deployment Runbook

**Version:** 1.0.0
**Stack:** WordPress → n8n → OpenRouter (free) → ClickUp (HITL) → Postiz (self-hosted)
**Output:** 8 platform variants per source post, scheduled at optimal times.

---

## 0. Prerequisites Checklist

Before deploying, confirm you have:

- [ ] **VPS** (4GB RAM, Ubuntu 22.04+) — hosts n8n + Postiz
- [ ] **Domain** with subdomains: `n8n.yourdomain.com`, `postiz.yourdomain.com`
- [ ] **Cloudflare Tunnel** or **Nginx + Certbot** for SSL
- [ ] **WordPress site** at wordpressistic.com with admin access
- [ ] **OpenRouter account** (free) → API key from https://openrouter.ai/keys
- [ ] **Gmail account** for OAuth (n8n approval/digest emails)
- [ ] **Google Sheet** for run logging
- [ ] **ClickUp API token** (Settings → Apps → Generate)
- [ ] **All 8 social accounts** ready to connect: LinkedIn, X, FB, IG, Pinterest, GBP, Medium, Threads

---

## 1. Deployment Order (do in this exact sequence)

### Step 1 — Deploy Postiz (15 min)
Follow `docs/postiz-setup.md` sections 1–4.
**Verify:** Login works, all 8 social accounts connected, API key generated.

### Step 2 — Deploy n8n (10 min)
```bash
mkdir -p /opt/n8n && cd /opt/n8n
cat > docker-compose.yaml <<'EOF'
services:
  n8n:
    image: n8nio/n8n:latest
    restart: unless-stopped
    ports:
      - "5678:5678"
    environment:
      N8N_HOST: n8n.yourdomain.com
      N8N_PROTOCOL: https
      WEBHOOK_URL: https://n8n.yourdomain.com/
      GENERIC_TIMEZONE: America/New_York
      N8N_RUNNERS_ENABLED: "true"
      N8N_ENCRYPTION_KEY: "<generate-32-char-random>"
    volumes:
      - n8n-data:/home/node/.n8n
volumes:
  n8n-data:
EOF
docker compose up -d
```

### Step 3 — Set n8n Environment Variables (5 min)
n8n UI → **Settings → Variables** → add:

| Key | Value | Notes |
|---|---|---|
| `WPISTIC_HMAC_SECRET` | `<32-char random>` | Same value goes into WP plugin |
| `WPISTIC_LOG_SHEET_ID` | `<Sheet ID>` | Create blank Google Sheet, copy ID from URL |
| `WPISTIC_APPROVER_EMAIL` | `you@wordpressistic.com` | Where approval emails go |
| `WP_SITE_URL` | `https://wordpressistic.com` | |
| `N8N_WORKFLOW_2_WEBHOOK` | `https://n8n.yourdomain.com/webhook/wpistic-approval-create` | Set after importing W2 |
| `CLICKUP_APPROVAL_LIST_ID` | `901817939022` | ✅ Already created |
| `CLICKUP_FAILED_LIST_ID` | `901817939023` | ✅ Already created |
| `POSTIZ_BASE_URL` | `https://postiz.yourdomain.com` | No trailing slash |
| `WPISTIC_PROMPT_LINKEDIN` | (paste from `prompts/brand-voice-prompts.md`) | One per platform |
| `WPISTIC_PROMPT_X` | (paste) | |
| `WPISTIC_PROMPT_FACEBOOK` | (paste) | |
| `WPISTIC_PROMPT_INSTAGRAM` | (paste) | |
| `WPISTIC_PROMPT_PINTEREST` | (paste) | |
| `WPISTIC_PROMPT_GBP` | (paste) | |
| `WPISTIC_PROMPT_MEDIUM` | (paste) | |
| `WPISTIC_PROMPT_THREADS` | (paste) | |

### Step 4 — Set up n8n Credentials (10 min)
n8n UI → **Credentials → New**:

1. **OpenRouter API Key** (HTTP Header Auth)
   - Header: `Authorization`
   - Value: `Bearer sk-or-v1-...`

2. **Postiz API Key** (HTTP Header Auth)
   - Header: `Authorization`
   - Value: `Bearer postiz_...`

3. **ClickUp API Token** (HTTP Header Auth)
   - Header: `Authorization`
   - Value: `pk_<your-token>` (no Bearer prefix for ClickUp)

4. **Gmail OAuth** — follow n8n's OAuth flow, authorize sending only.

5. **Google Sheets OAuth** — same flow, authorize Sheets read/write.

### Step 5 — Import 4 Workflows (5 min)

In n8n UI → **Workflows → Import from File** for each:

1. `01-ingestion-and-ai-generation.json` → save, copy webhook URL (e.g., `https://n8n.../webhook/wpistic-ingest`)
2. `02-approval-gate.json` → save, copy webhook URL → paste into `N8N_WORKFLOW_2_WEBHOOK` variable
3. `03-scheduling-and-publishing.json` → save, copy ClickUp webhook URL
4. `04-monitoring-and-digest.json` → save, no external webhook needed

**Activate all 4 workflows.**

### Step 6 — Prepare Google Sheet (3 min)

Create blank Google Sheet → 2 tabs:

**Tab 1: "Runs"** — columns: `run_id | step | status | platform_count | timestamp_utc`
**Tab 2: "Posts"** — columns: `run_id | platform | scheduled_at | postiz_post_id | status | logged_at`

Copy the Sheet ID (from URL between `/d/` and `/edit`) → set as `WPISTIC_LOG_SHEET_ID` env var.

### Step 7 — Configure ClickUp Webhook (5 min)

ClickUp triggers Workflow 3 when you set a parent task to "Approved":

1. In ClickUp **Settings → Apps → Webhooks** → New Webhook
2. URL: `https://n8n.yourdomain.com/webhook/wpistic-clickup-status`
3. Events: `taskStatusUpdated`
4. Filter: List = `Social Approval Queue` (ID `901817939022`)
5. Save → ClickUp will ping n8n on every status change.

### Step 8 — Install WordPress Plugin (3 min)

1. ZIP `wp-plugin/wpistic-content-automation.php` (or upload as folder)
2. WP Admin → Plugins → Add New → Upload Plugin
3. Activate
4. **Settings → Wpistic Automation:**
   - Webhook URL: `https://n8n.yourdomain.com/webhook/wpistic-ingest`
   - Shared Secret: same as `WPISTIC_HMAC_SECRET` env var

### Step 9 — Add "Approved" Status to Approval List (2 min)

Open the **Social Approval Queue** list in ClickUp:
1. Click status indicator → **Manage Statuses**
2. Add custom statuses: `Pending`, `In Review`, `Approved`, `Rejected`, `Edit Needed`
3. Set `Pending` as default

---

## 2. Test Plan (do not skip)

### Test 1: WP → n8n Webhook Hit
- Publish a draft post in WordPress.
- Check n8n **Executions** tab → you should see Workflow 1 fire within 5 seconds.
- ✅ Pass: workflow runs, Sheet "Runs" tab shows new row.
- ❌ Fail: check WP plugin Settings page for webhook URL typo, check n8n is reachable.

### Test 2: AI Generation Quality
- Pull the test execution → look at "AI: Generate Platform Variant" output for each platform.
- Validate each variant returns valid JSON with the right shape.
- ✅ Pass: 8 outputs, all parseable.
- ❌ Fail: hit OpenRouter rate limit (50/day free) — wait or upgrade. Or prompts need tightening.

### Test 3: ClickUp Approval Task Created
- Workflow 1 should hand off to Workflow 2 → check "Social Approval Queue" list.
- ✅ Pass: 1 parent task + 8 subtasks, each with platform-specific content.
- ❌ Fail: check ClickUp API token, list ID env var.

### Test 4: Approval Email
- You should receive the digest email with "Review in ClickUp" button.
- ✅ Pass: email arrives within 60s.
- ❌ Fail: Gmail OAuth issue, or `WPISTIC_APPROVER_EMAIL` typo.

### Test 5: Approve → Schedule
- In ClickUp, set the parent task status to **Approved**.
- ClickUp webhook fires → Workflow 3 runs.
- ✅ Pass: 8 posts appear in Postiz dashboard, scheduled at staggered times.
- ❌ Fail: check ClickUp webhook setup, Postiz API key, integration ID mapping.

### Test 6: Failure Recovery
- Disconnect ONE social account in Postiz (e.g., Threads).
- Approve another post.
- ✅ Pass: 7 platforms scheduled, 1 task created in "Failed Posts Recovery" list with error.

### Test 7: Daily Monitoring
- Wait for 8am UTC daily cron, OR manually trigger Workflow 4.
- ✅ Pass: stats compute correctly. If failures > 0, alert email arrives.

---

## 3. Daily Operations

### Morning Routine (5 min)
1. Check inbox for approval emails → click ClickUp link → review variants → set parent status to **Approved**.
2. Skim "Failed Posts Recovery" list → fix any blocked publishes.

### Weekly Routine (15 min, Mondays)
1. Read weekly digest email.
2. Review per-platform performance in Postiz analytics.
3. Tighten brand-voice prompts if any platform's content feels off (edit env vars in n8n, no redeploy).

---

## 4. Rollback Procedure

If something breaks production:

### Disable everything fast:
```bash
# In n8n UI: deactivate all 4 workflows (toggle top-right of each).
# Or via CLI:
docker exec n8n n8n update:workflow --id=<workflow-id> --active=false
```

### Disable WP plugin:
WP Admin → Plugins → Deactivate "Wpistic Content Automation Bridge". Webhooks stop firing.

### Pause Postiz scheduling:
Postiz UI → Bulk select scheduled posts → Cancel.

### Restore from backup:
```bash
docker run --rm -v n8n-data:/data -v $PWD:/backup alpine tar xzf /backup/n8n-backup.tar.gz -C /
```

---

## 5. Common Failures + Fixes

| Symptom | Likely Cause | Fix |
|---|---|---|
| WP publish doesn't trigger | Wrong webhook URL or HMAC mismatch | Re-check Settings → Wpistic Automation |
| AI returns bad JSON | OpenRouter free model variance | Workflow 1 retry chain handles 3 models — if all fail, switch to Ollama (see Section 6) |
| All 8 platforms fail | Postiz API key invalid or Postiz down | `docker compose ps postiz` → restart, regenerate key |
| Single platform fails | OAuth token expired for that platform | Reconnect in Postiz UI |
| No approval email arrives | Gmail OAuth expired | Re-authorize in n8n credentials |
| Approval doesn't trigger Workflow 3 | ClickUp webhook misconfigured | Test webhook from ClickUp UI, check filter |
| Posts schedule but don't publish | Postiz worker crashed | `docker compose logs postiz` → restart |
| Duplicate posts | Idempotency cache cleared | Normal after restart — won't fire same `run_id` twice within 24h |

---

## 6. Optional: Switch from OpenRouter to Local Ollama

If OpenRouter free tier rate-limits you out (50 req/day = 5–6 full pipelines):

```bash
# On VPS or separate GPU server
docker run -d --gpus all -v ollama:/root/.ollama -p 11434:11434 --name ollama ollama/ollama
docker exec ollama ollama pull llama3.1:8b
docker exec ollama ollama pull gemma2:9b
```

In Workflow 1, replace the OpenRouter HTTP Request URL:
- From: `https://openrouter.ai/api/v1/chat/completions`
- To: `http://ollama:11434/api/chat` (if same Docker network) OR `http://your-vps-ip:11434/api/chat`

Adjust the JSON body slightly:
```json
{
  "model": "llama3.1:8b",
  "messages": [...],
  "stream": false,
  "format": "json"
}
```

Response shape changes — update "Parse AI Variant" node:
```js
const raw = $json.message?.content || '{}';
```

---

## 7. Future Enhancements (v1.1+)

- [ ] **Image cropping** — ImageMagick step in n8n to auto-crop 4 platform-specific dimensions.
- [ ] **Engagement tracking** — pull metrics from Postiz/native APIs into Sheet (which posts converted).
- [ ] **A/B testing** — generate 2 variants per platform, post one, swap if low engagement.
- [ ] **Topic suggestions** — Workflow 5: scrape competitor RSS + Google Trends → propose weekly topics.
- [ ] **Repurpose old content** — cron quarterly: re-run top blog posts through pipeline (refreshed angle).
- [ ] **WP form for manual topics** — Gravity Forms / WPForms → POST to `/wp-json/wpistic/v1/manual-trigger`.

---

## 8. Cost Snapshot

| Component | Monthly | Notes |
|---|---|---|
| VPS (4GB) | $12–20 | Hetzner CX22, DigitalOcean, etc. |
| Domain | $1 | Cloudflare-registered |
| OpenRouter | $0 | Free tier (or ~$3 if you upgrade for higher limits) |
| Postiz self-hosted | $0 | OSS |
| n8n self-hosted | $0 | OSS |
| ClickUp | $0 | Free tier sufficient |
| **Total** | **~$13–21** | For unlimited posts across 8 platforms |

vs. Buffer/Hootsuite + Make.com + Jasper = $80–150/month.

---

## 9. Support + Maintenance

- **Logs:** all runs visible in Google Sheet "Runs" + "Posts" tabs.
- **Failures:** all in ClickUp "Failed Posts Recovery" list.
- **Voice tuning:** edit `WPISTIC_PROMPT_*` env vars in n8n — no code redeploy.
- **Schedule changes:** edit "Calculate Optimal Slot" node in Workflow 3 (the `SCHEDULES` object).
- **Add a 9th platform:** add to `platforms` array in Workflow 1's "Fan Out" node + add prompt env var + connect in Postiz.

System is fully observable. No silent failures by design.
