# Postiz Self-Hosted Setup for Wpistic Automation

## Why Self-Hosted

| Option | API Access | Cost | Best For |
|---|---|---|---|
| **Postiz Self-Hosted** ⭐ | Full | $0 (your VPS) | This automation |
| Postiz Cloud Free | None | $0 | Manual posting only |
| Postiz Cloud Pro | Full | ~$15/mo | If you skip self-hosting |

Self-hosted is the only zero-cost option with full API.

---

## 1. Deploy Postiz (Docker, 5 minutes)

```bash
# On your VPS (Ubuntu 22.04+, 2GB RAM minimum)
mkdir -p /opt/postiz && cd /opt/postiz

# Use official docker-compose
curl -O https://raw.githubusercontent.com/gitroomhq/postiz-app/main/docker-compose.dev.yaml
mv docker-compose.dev.yaml docker-compose.yaml
```

Edit `docker-compose.yaml` essentials:

```yaml
services:
  postiz:
    image: ghcr.io/gitroomhq/postiz-app:latest
    container_name: postiz
    restart: unless-stopped
    environment:
      MAIN_URL: "https://postiz.yourdomain.com"
      FRONTEND_URL: "https://postiz.yourdomain.com"
      NEXT_PUBLIC_BACKEND_URL: "https://postiz.yourdomain.com/api"
      JWT_SECRET: "<generate-32-char-random>"
      DATABASE_URL: "postgresql://postiz:postiz@postiz-postgres:5432/postiz"
      REDIS_URL: "redis://postiz-redis:6379"
      BACKEND_INTERNAL_URL: "http://localhost:3000"
      IS_GENERAL: "true"
      DISABLE_REGISTRATION: "false"
      STORAGE_PROVIDER: "local"
      UPLOAD_DIRECTORY: "/uploads"
      NEXT_PUBLIC_UPLOAD_DIRECTORY: "/uploads"
    volumes:
      - postiz-config:/config/
      - postiz-uploads:/uploads/
    ports:
      - "5000:5000"
```

```bash
docker compose up -d
docker compose logs -f postiz
```

Reverse-proxy via Cloudflare Tunnel or Nginx → SSL → done.

---

## 2. Connect Social Accounts (One-Time)

Login to `https://postiz.yourdomain.com` → **Channels** → connect each:

| Platform | Auth Method | Notes |
|---|---|---|
| LinkedIn | OAuth | Personal + Page both supported |
| X (Twitter) | OAuth | Free tier limits to 50 posts/day |
| Facebook | OAuth | Need a Page (not personal) |
| Instagram | OAuth via FB | Must be Business or Creator account |
| Pinterest | OAuth | Need a board to post to |
| Google Business | OAuth | Need a verified GBP location |
| Medium | API Token | Generate at medium.com/me/settings → Integration tokens |
| Threads | OAuth | Currently in beta — connect via Meta dev console |

**Pro tip:** Connect each account, then post one test from Postiz UI to confirm it works BEFORE wiring n8n. Saves debugging hell.

---

## 3. Generate API Key

In Postiz UI:
1. **Settings → Developers → API Keys**
2. Click **Generate New Key**
3. Copy the token (starts with `postiz_...`)
4. Store as `POSTIZ_API_KEY` env var in n8n

---

## 4. Fetch Integration IDs (Critical Step)

Postiz API doesn't accept platform names — it needs integration IDs (UUIDs) per connected account.

**One-time fetch:**

```bash
curl -H "Authorization: Bearer $POSTIZ_API_KEY" \
  https://postiz.yourdomain.com/api/integrations
```

Sample response:
```json
[
  {
    "id": "clx9f8h2k0001abc123",
    "name": "Wordpressistic LinkedIn",
    "identifier": "linkedin",
    "providerIdentifier": "linkedin",
    "picture": "...",
    "type": "social"
  },
  {
    "id": "clx9f8h2k0002abc456",
    "identifier": "x",
    ...
  }
]
```

Workflow 3's **"Fetch Postiz Integrations"** node calls this on every run — no manual mapping needed. The mapping code matches `identifier` → your platform key automatically.

**If your Postiz uses different identifiers** (rare), edit `POSTIZ_KEYS` in the "Map to Postiz Integration ID" node:

```javascript
const POSTIZ_KEYS = {
  linkedin:  ['linkedin', 'linkedin-page'],
  x:         ['x', 'twitter'],
  facebook:  ['facebook', 'facebook-page'],
  instagram: ['instagram', 'instagram-standalone'],
  pinterest: ['pinterest'],
  gbp:       ['google-business', 'google'],
  medium:    ['medium'],
  threads:   ['threads']
};
```

---

## 5. API Reference — Endpoints Used

### `POST /api/posts` — Schedule a post
```json
{
  "type": "schedule",
  "date": "2026-05-08T14:00:00.000Z",
  "shortLink": false,
  "tags": ["wpistic-auto"],
  "posts": [
    {
      "integration": { "id": "<integration-id>" },
      "value": [
        { "content": "Post body here", "image": [{ "url": "https://..." }] }
      ],
      "group": "<run_id>"
    }
  ]
}
```

For X threads, `value` is an array with multiple `{content, image}` items.

### `GET /api/integrations` — List connected accounts
Returns array. Cache for ~1hr in n8n static data if you hit rate limits.

### `GET /api/posts` — List scheduled/published posts
Useful for monitoring — Workflow 4 can poll this for status updates.

---

## 6. Image Handling

Postiz accepts image URLs directly — but each platform has dimension requirements:

| Platform | Recommended | Min |
|---|---|---|
| LinkedIn | 1200×627 | 552×276 |
| Instagram (square) | 1080×1080 | 320×320 |
| Instagram (portrait) | 1080×1350 | — |
| Facebook | 1200×630 | 600×315 |
| Pinterest | 1000×1500 (2:3) | 600×900 |
| Google Business | 1200×900 (4:3) | 720×540 |
| X | 1200×675 (16:9) | — |
| Threads | 1080×1080 | — |

**If your WP featured image is 1920×1080**, it works for most platforms but Instagram/Pinterest will crop badly.

**Future enhancement** (not in v1): add an n8n ImageMagick step that pre-crops 4 versions and uploads to Postiz storage.

For now: ensure your blog featured images are at least 1200×1200 (square base allows safe crops).

---

## 7. Webhook from Postiz → n8n (Optional)

Postiz can ping n8n on publish success/failure. Useful for a "post went live" log entry.

**In Postiz:** Settings → Webhooks → Add URL: `https://your-n8n.com/webhook/postiz-status`

**In n8n:** Workflow 4 already has a webhook entry point. Add this node optionally if you want real-time status (vs. cron polling).

---

## 8. Rate Limits to Watch

| Platform | Limit | Workaround |
|---|---|---|
| X (free tier) | 50 posts/day | Stagger schedule already handles this |
| LinkedIn | ~150/day per account | Not a real concern |
| Instagram Graph API | 200 posts/24h per user | Same |
| Facebook Pages | 200/24h per page | Same |
| Pinterest | No documented hard limit | Spread out for sanity |
| GBP | 1 post/24h recommended | Workflow 3 schedule respects this |

If you hit rate limits, Postiz API returns `429` — Workflow 3's retry logic catches it and pushes to Failed Posts list.

---

## 9. Troubleshooting

**"Integration not found for platform: X"**
→ Check that platform is connected in Postiz UI. Re-fetch `/api/integrations`.

**Posts schedule but never publish**
→ Check Postiz worker container is running: `docker compose logs postiz`. Worker handles the actual publishing at scheduled time.

**Images don't upload**
→ Postiz expects publicly accessible image URLs OR direct upload via `/api/media`. Easiest: use WP featured image URL (already public).

**OAuth token expired**
→ Postiz auto-refreshes most. If not, reconnect platform in UI. n8n will surface the error in Failed Posts list.
