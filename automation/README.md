# Wpistic Content Automation Engine v1.0

**Full-stack content automation for Wordpressistic**
WP → n8n → AI (OpenRouter free) → ClickUp approval → Postiz → 8 platforms

---

## What's Inside

```
automation/
├── wp-plugin/
│   └── wpistic-content-automation.php   ← WordPress webhook bridge (HMAC-signed)
├── n8n-workflows/
│   ├── 01-ingestion-and-ai-generation.json   ← 3 triggers → AI generates 8 variants
│   ├── 02-approval-gate.json                  ← ClickUp HITL queue + email digest
│   ├── 03-scheduling-and-publishing.json     ← Smart stagger → Postiz API
│   └── 04-monitoring-and-digest.json          ← Daily fail alerts + weekly digest
├── prompts/
│   └── brand-voice-prompts.md                 ← 8 platform-specific prompts (env vars)
├── docs/
│   ├── postiz-setup.md                        ← Self-hosted Postiz deployment
│   └── runbook.md                              ← Full deployment + ops guide
└── README.md                                   ← This file
```

---

## How It Works

```
TRIGGER (3 ways)              GENERATE              APPROVE              SCHEDULE
─────────────────             ────────              ───────              ────────
WP publish ─┐
Manual topic├─► Workflow 1 ──► OpenRouter free ──► Workflow 2 ──► ClickUp parent task
RSS feed  ─┘    (idempotent)   (3-model fallback)   (HITL queue)   (8 platform subtasks)
                                                                          │
                                                          You approve ◄───┤
                                                                          ▼
                                                          Workflow 3 ──► Postiz API
                                                          (stagger sched) (8 platforms)
                                                                          │
                                                                          ▼
                                                          Workflow 4 ──► Daily/weekly
                                                          (monitoring)   email digest
```

---

## Key Features

✅ **3 entry points, 1 pipeline** — WP publishes, manual topics, RSS curation
✅ **8 platforms** — LinkedIn, X, Facebook, Instagram, Pinterest, GBP, Medium, Threads
✅ **Free AI** — OpenRouter free tier (3-model fallback) with optional Ollama swap
✅ **Brand voice** — Wordpressistic premium tone enforced per platform
✅ **HITL approval** — nothing publishes without you reviewing in ClickUp
✅ **Smart scheduling** — per-platform optimal posting times, staggered to avoid spam flags
✅ **Idempotent** — duplicate triggers ignored, retries safe
✅ **Full observability** — every run logged to Google Sheet
✅ **No silent failures** — failures route to ClickUp recovery list + email
✅ **~$15/month** — vs $80–150/mo for Buffer + Make.com + Jasper

---

## Quick Start

1. **Read the runbook:** `docs/runbook.md` — 9 deployment steps, ~60 minutes total.
2. **Deploy Postiz first:** `docs/postiz-setup.md` — connect all 8 social accounts.
3. **Deploy n8n second:** import 4 workflows, set env vars, configure credentials.
4. **Install WP plugin third:** activate, paste webhook URL + HMAC secret.
5. **Configure ClickUp webhook:** triggers Workflow 3 on "Approved" status change.
6. **Run Test Plan** (Section 2 of runbook) before going live.

---

## ClickUp Lists (already created in your workspace)

- **Social Approval Queue** — list ID `901817939022` — https://app.clickup.com/9018592130/v/l/li/901817939022
- **Failed Posts Recovery** — list ID `901817939023` — https://app.clickup.com/9018592130/v/l/li/901817939023

---

## Brand Voice Discipline

Every platform variant is generated through Wordpressistic brand voice rules:
- Confident, strategic, outcome-focused
- Zero hype words ("game-changer", "leverage", "synergy")
- No AI tells ("In conclusion", "Let's dive in", "It's not just X, it's Y")
- Examples before theory

If output drifts, edit the `WPISTIC_PROMPT_*` env vars in n8n. No code redeploy needed.

---

## Built For Solo Operators

This system runs unattended except for:
- Daily 5-min approval check (one click in ClickUp)
- Weekly 15-min digest review

Everything else is automated. Failures self-route to a recovery queue. You get an email if anything needs human attention.

---

## Support

- Full docs: `docs/runbook.md`
- Issues: log → check Google Sheet `Runs` tab + n8n Executions panel
- Ops emergencies: `docs/runbook.md` Section 4 (Rollback) + Section 5 (Common Failures)
