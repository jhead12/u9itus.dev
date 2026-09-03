# Campaign Video & Earn-Per-View API System

**Status:** Design proposal — implementable against the current CRTV v4 codebase (`sirgawain0x/crtv3`).
**Scope:** (1) A campaign-scoped video API where a campaign sponsor (the u9itus platform) pays the cost of a view so the viewer watches for free; (2) a Chrome/Browser extension that connects a viewer wallet and pays the viewer USDC for verified watch time, with the campaign budget debited; (3) an **embed code** under each video that plays on other platforms (Bluesky, X, any site) and a **wallet-aware "special like" button** that lets embedded/extension viewers contribute — earning on a campaign or tipping the creator with one tap.

## Resolved decisions (lock-in)

| Decision | Resolution | Implication |
|---|---|---|
| Budget custody | **Off-chain first.** Campaign budget tracked in the `campaigns` Supabase table; a platform hot wallet funds settlements directly. `CampaignEscrow.sol` is a **v2 trust-minimized upgrade**, not in scope for v1. | No on-chain escrow contract needed to ship. §6 is documented as the v2 path. |
| Reward asset | **USDC only.** Viewers earn USDC to their connected wallet. | Drop HeartBit-NFT / MeToken reward variants. The HeartBit system is still **reused as the proof-of-watch mechanism**, just not as the payout asset. |
| Creator payout routing | **Reuse Splits.org.** `creator_usdc` is sent to the existing `video_assets.splits_address`, which distributes among collaborators per the already-configured `video_collaborators` bps weights. | Zero new payout plumbing. Settlement job calls the existing Splits `distribute()`; if `splits_address` is null, fall back to direct transfer to `creator_id`. |
| Rate model | **Flat CPV per second.** `viewer_usdc = seconds × cpv_rate` (default `0.01/s`, matching `USDC_TIP_RATE_PER_SECOND`). | Mirrors the existing HeartBit hold model exactly — simple, predictable, auditable. No completion bonus / tiering / completion-cap complexity. |
| Extension distribution | **Sideload beta, then Chrome Web Store.** Ship a developer-mode / unpacked-load beta to a small group first (fast iteration, no review delays); publish to the Web Store under u9itus once stable. | Beta first; Web Store for reach + auto-updates once the API + UI are stable. Firefox/AMO is a later option (MV3 is largely portable). |
| Geo / KYC | **Geo-restrict earning in flagged jurisdictions.** *Viewing* stays open everywhere; *earning* is blocked where earn-per-view likely triggers money-transmitter rules. | Adds a region check at the heartbeat endpoint; no KYC friction for allowed viewers. Re-evaluate a KYC-gate-above-threshold model later using the repo's Coinbase onramp / Orb primitives if caps are needed. |

---

## 1. Goals & model

- A **campaign** is a sponsor-funded bucket of budget attached to one or more videos.
- When a **sponsored video** is played, the **viewer pays nothing** (the existing MeToken gate is bypassed) and instead **earns USDC** per verified second of watch time.
- The **campaign budget** is debited to cover: viewer earnings + creator share + platform fee.
- Watch time is proven by **HeartBit composite hashes + signed heartbeats** (reuse the existing HeartBit relayer), not by a naive play event — this is the anti-fraud backbone.
- A **browser extension** is the first-class client: it connects the viewer's wallet (EOA or ERC-4337 smart account), injects the proof-of-watch stream, and settles earnings to the viewer's wallet via the existing gasless paymaster.

### Money flow (v1 — off-chain budget)

```
                ┌─────────────┐   funds   ┌────────────────────┐
                │  Sponsor     │ ───────▶  │  Campaign Budget    │
                │ (u9itus plat)│           │  (campaigns table + │
                └─────────────┘           │   platform hot wallet)│
                                          └──────────┬───────────┘
                                                     │ per verified second
                                    ┌────────────────┼────────────────┐
                                    ▼                ▼                ▼
                              viewer earns      creator share     platform fee
                              (extension →      (existing splits  (u9itus)
                               wallet, USDC)    _address, if set)
```

### What we reuse vs. build

| Reuse (already in repo) | Build (new) |
|---|---|
| `CampaignStickers.sol` + `campaign_stickers` tables | `campaigns` table + budget ledger |
| `sticker_tips` ledger shape | `campaign_view_earnings` table + settlement job |
| HeartBit `unSignedMintHeartBit`, `buildCompositeHash`, `MAX_HOLD_SECONDS`, `USDC_TIP_RATE_PER_SECOND` | Campaign HeartBit composite namespace + CPV rate config |
| `require-wallet` EIP-1271 auth headers | Extension → API reuse of those exact headers |
| Alchemy Account Kit paymaster (gasless) | Platform-funded USDC transfer to viewer smart account |
| `checkMeTokenAccess` / `streams/access/[playbackId]` | Campaign-sponsored gate branch |
| `checkBotIdDeep` + `rateLimiters` | Per-view heartbeat rate limiting |

### 1.3 Platform fee model — how CRTV earns

CRTV v4 today is **creator-first passthrough**: across most rails the platform captures nothing automatically. A review of the codebase shows exactly one built-in platform revenue stream, plus the new one this campaign system introduces:

| Rail | Who earns | Platform cut today | Source |
|---|---|---|---|
| NFT **primary sale** | Creator + platform | ✅ `defaultPlatformFeeBps` (thirdweb `TokenERC721`) | `CreatorIPCollectionFactory.sol` |
| NFT **secondary royalty** (EIP-2981) | Creator (configurable recipient) | ❌ | `CreatorIPCollection.sol` (5% default → creator) |
| **MeToken** buy / contribution | Creator | ❌ — transfer goes straight to creator | `useMeTokenPurchase.ts` ("for now we transfer directly to owner") |
| **Splits.org** distribution | Collaborators (creator-chosen) | ❌ | `video_assets.splits_address` + `video_collaborators` |
| **Sticker tips** (HeartBit) | Creator | ❌ | `sticker_tips` ledger |
| **Gas sponsorship** | — | cost to platform (Alchemy paymaster) | `useGasSponsorship` |
| **Campaign spend** (this system) | Viewer + creator + **platform** | ✅ `platform_share_bps` of gross spend | `campaigns` + `record_campaign_view_earning()` |

**Design principle — fee on flow, not fee on use.** The Chrome extension and embed code are **free to install and free to use**. CRTV earns from the **spend those surfaces generate**, not from tolling them:

- A **sponsor** funds a campaign (budget).
- The **extension** (desktop) and **embed** (Bluesky / X / any site) deliver verified views via `POST /api/campaigns/heartbeat`.
- Each verified view produces `gross_usdc = seconds × cpv_rate`.
- Settlement splits gross into `viewer_share_bps` (default 50) + `creator_share_bps` (30) + **`platform_share_bps` (20)**.
- **That 20% is CRTV's fee, and it scales with extension + embed usage** — more embeds placed, more extension installs, more verified views → more platform revenue, automatically. Embed and extension are economically identical to CRTV (both are just heartbeat-API clients), so no per-surface fee logic is needed.

**Critical nuance — tax the sponsor's gross, not the viewer's net.** The viewer always gets their full `viewer_share` of the gross; the platform's 20% is carved out *alongside* the creator's share, *before* the viewer portion is computed. Taking a cut out of the viewer's earnings would erode the incentive that drives adoption. The fee stays on the sponsor side.

> **Optional second lever (additive, not required):** today the MeToken "Buy"/contribution flow transfers straight to the creator with no cut. If those contributions happen *through the embed* (a Bluesky viewer taps the special-like-as-tip), that flow could be routed through the same settlement job and take a `platform_share_bps` there too — turning the embed into a monetization surface for **non-campaign** content.

### 1.4 Upload, IPFS & Story IP-ID — what already exists

This campaign system does **not** introduce a new storage pipeline. It binds campaigns to videos that are already created through the existing upload + IP registration flow.

**Existing upload pipeline (API-mediated):**
1. **File → IPFS:** `GroveVideoUploader` → `uploadToGrove(file)` → `POST /api/ipfs/upload` → `groveService.uploadFile(file)` → `{ url, hash }`; `video_assets.metadata_uri` = the IPFS hash. Grove is IPFS-compatible; the repo also has Lighthouse/Storacha hybrid + Filecoin + IPNS gateway fallback.
2. **Asset → Livepeer:** a separate upload creates the transcoded asset and the `playback_id` used for streaming.
3. **Story Protocol IP registration:** the video's NFT (minted via `CreatorIPCollection`, CREATE2-per-creator) is registered as an IP Asset on Story Protocol → `video_assets.story_ip_id`; PIL license terms attached (`StoryLicenseSelector` in the upload config step). Fields: `story_ip_registered`, `story_ip_id`, `story_ip_registration_tx`, `story_ip_registered_at`, `story_license_terms_id`, `story_license_template_id`.

**What this means for campaigns:** a "campaign video" is simply a `video_assets` row (already carrying `metadata_uri` + `playback_id` + `story_ip_id`) bound to a campaign via the `campaign_videos` table. No new storage path.

**Gap to close for programmatic upload:** the existing `/api/ipfs/upload` is a thin *client-facing* route (bot guard + rate limit, **no wallet auth**). If sponsors or automated pipelines need to push campaign videos without the web UI, add a **wallet-authenticated** route that does upload + publish + (optional) Story IP registration + campaign-bind in one call:

- `POST /api/campaigns/upload` — `requireWalletAuthFor(req, body.sponsorWallet)`; multipart file → Grove → Livepeer asset → `video_assets` insert → `campaign_videos` insert → optional Story IP registration. This reuses `groveService`, the Livepeer client, `services/story-protocol.ts`, and the campaign CRUD — no new primitives.

---

## 2. Data model (Supabase migrations)

All writes go through service-role API routes (mirrors the existing `campaign_stickers` RLS pattern: public SELECT, service-role INSERT/UPDATE).

### 2.1 `campaigns`

```sql
-- supabase/migrations/<ts>_campaigns.sql
CREATE TABLE IF NOT EXISTS public.campaigns (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  -- link to existing on-chain campaign sticker (optional but recommended)
  sticker_token_id bigint,                       -- -> campaign_stickers.token_id
  proposal_id text,                               -- Snapshot proposal (matches CampaignStickers)
  sponsor_wallet text NOT NULL,                   -- canonical lowercase eth; funds the budget (u9itus platform)
  name text NOT NULL,
  status text NOT NULL DEFAULT 'active'           -- active | paused | exhausted | closed
        CHECK (status IN ('active','paused','exhausted','closed')),
  total_budget_usdc numeric NOT NULL,             -- USDC, 6-decimals scale stored as numeric
  spent_usdc numeric NOT NULL DEFAULT 0,
  cpv_rate_usdc_per_sec numeric NOT NULL,         -- cost per verified second (default 0.01)
  viewer_share_bps integer NOT NULL DEFAULT 5000, -- 50% of spend goes to viewer
  creator_share_bps integer NOT NULL DEFAULT 3000,-- 30% to creator
  platform_share_bps integer NOT NULL DEFAULT 2000,
  -- safety caps
  max_pay_per_view_usdc numeric NOT NULL DEFAULT 1,
  max_pay_per_viewer_per_day_usdc numeric NOT NULL DEFAULT 5,
  starts_at timestamptz,
  ends_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT campaign_wallet_lower CHECK (sponsor_wallet ~ '^0x[a-f0-9]{40}$'),
  CONSTRAINT campaign_shares_sum CHECK (
    viewer_share_bps + creator_share_bps + platform_share_bps = 10000
  ),
  CONSTRAINT campaign_budget_positive CHECK (total_budget_usdc > 0)
);
CREATE INDEX idx_campaigns_status ON public.campaigns (status) WHERE status = 'active';
CREATE INDEX idx_campaigns_sponsor ON public.campaigns (sponsor_wallet);
ALTER TABLE public.campaigns ENABLE ROW LEVEL SECURITY;
CREATE POLICY campaigns_select_all ON public.campaigns FOR SELECT TO anon, authenticated USING (true);
GRANT SELECT ON public.campaigns TO anon, authenticated;
REVOKE INSERT, UPDATE, DELETE ON public.campaigns FROM anon, authenticated;
```

### 2.2 `campaign_videos` (M:N — a campaign funds multiple videos; a video can be in several)

```sql
CREATE TABLE IF NOT EXISTS public.campaign_videos (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  campaign_id uuid NOT NULL REFERENCES public.campaigns(id) ON DELETE CASCADE,
  video_id integer NOT NULL,                      -- -> video_assets.id (no hard FK; matches existing style)
  -- override campaign defaults per video if desired
  cpv_rate_usdc_per_sec numeric,                  -- NULL = inherit campaign
  weight integer NOT NULL DEFAULT 1,             -- rotation weight when campaign has many videos
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (campaign_id, video_id)
);
CREATE INDEX idx_campaign_videos_video ON public.campaign_videos (video_id);
CREATE INDEX idx_campaign_videos_campaign ON public.campaign_videos (campaign_id);
ALTER TABLE public.campaign_videos ENABLE ROW LEVEL SECURITY;
CREATE POLICY campaign_videos_select_all ON public.campaign_videos FOR SELECT TO anon, authenticated USING (true);
GRANT SELECT ON public.campaign_videos TO anon, authenticated;
REVOKE INSERT, UPDATE, DELETE ON public.campaign_videos FROM anon, authenticated;
```

### 2.3 `campaign_view_earnings` (the earn-per-view ledger — extends `sticker_tips` shape)

```sql
CREATE TABLE IF NOT EXISTS public.campaign_view_earnings (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  campaign_id uuid NOT NULL REFERENCES public.campaigns(id),
  video_id integer NOT NULL,
  viewer_wallet text NOT NULL,                   -- lowercase eth
  -- proof-of-watch (reuse HeartBit composite semantics)
  composite_hash text NOT NULL,                   -- videoHash|campaignHash (or |stickerHash)
  seconds integer NOT NULL CHECK (seconds > 0 AND seconds <= 3600),  -- MAX_HOLD_SECONDS
  -- economics
  gross_usdc numeric NOT NULL,                    -- seconds * cpv_rate
  viewer_usdc numeric NOT NULL,                  -- gross * viewer_share_bps / 10000
  creator_usdc numeric NOT NULL,
  platform_usdc numeric NOT NULL,
  -- settlement
  status text NOT NULL DEFAULT 'pending'          -- pending | settled | failed | flagged
        CHECK (status IN ('pending','settled','failed','flagged')),
  viewer_payout_tx_hash text,
  creator_payout_tx_hash text,
  created_at timestamptz NOT NULL DEFAULT now(),
  settled_at timestamptz,
  CONSTRAINT cve_wallet_lower CHECK (viewer_wallet ~ '^0x[a-f0-9]{40}$'),
  -- idempotency: one settlement per composite_hash per viewer
  UNIQUE (composite_hash, viewer_wallet)
);
CREATE INDEX idx_cve_campaign ON public.campaign_view_earnings (campaign_id, created_at DESC);
CREATE INDEX idx_cve_viewer ON public.campaign_view_earnings (viewer_wallet, created_at DESC);
CREATE INDEX idx_cve_video ON public.campaign_view_earnings (video_id, created_at DESC);
CREATE INDEX idx_cve_pending ON public.campaign_view_earnings (status) WHERE status = 'pending';
ALTER TABLE public.campaign_view_earnings ENABLE ROW LEVEL SECURITY;
CREATE POLICY cve_select_all ON public.campaign_view_earnings FOR SELECT TO anon, authenticated USING (true);
GRANT SELECT ON public.campaign_view_earnings TO anon, authenticated;
REVOKE INSERT, UPDATE, DELETE ON public.campaign_view_earnings FROM anon, authenticated;
```

### 2.4 Atomic debit + insert RPC

A single atomic function debits the campaign and inserts the earning row, so concurrent heartbeats from the same extension can't overspend the budget or double-credit a viewer:

```sql
CREATE OR REPLACE FUNCTION public.record_campaign_view_earning(
  p_campaign_id uuid,
  p_video_id int,
  p_viewer text,
  p_composite text,
  p_seconds int,
  p_rate numeric,
  p_viewer_bps int,
  p_creator_bps int,
  p_platform_bps int
) RETURNS uuid AS $$
DECLARE
  v_row uuid;
  v_gross numeric;
  v_viewer numeric;
  v_creator numeric;
  v_platform numeric;
  v_per_view_cap numeric;
  v_per_viewer_daily_cap numeric;
  v_today_total numeric;
  v_total_budget numeric;
  v_spent numeric;
  v_status text;
BEGIN
  -- Lock the campaign row for the duration of the debit.
  SELECT total_budget_usdc, spent_usdc, status,
         max_pay_per_view_usdc, max_pay_per_viewer_per_day_usdc
    INTO v_total_budget, v_spent, v_status,
         v_per_view_cap, v_per_viewer_daily_cap
    FROM public.campaigns
    WHERE id = p_campaign_id
    FOR UPDATE;

  IF v_status IS NULL THEN
    RAISE EXCEPTION 'campaign not found';
  END IF;
  IF v_status <> 'active' THEN
    RAISE EXCEPTION 'campaign not active (status=%)', v_status;
  END IF;

  -- Economics.
  v_gross    := p_seconds * p_rate;
  v_viewer   := v_gross * p_viewer_bps   / 10000;
  v_creator  := v_gross * p_creator_bps  / 10000;
  v_platform := v_gross * p_platform_bps / 10000;

  -- Per-view cap.
  IF v_gross > v_per_view_cap THEN
    RAISE EXCEPTION 'per-view cap exceeded';
  END IF;

  -- Per-viewer daily cap (across all videos in this campaign).
  SELECT COALESCE(SUM(viewer_usdc), 0) INTO v_today_total
    FROM public.campaign_view_earnings
    WHERE campaign_id = p_campaign_id
      AND viewer_wallet = p_viewer
      AND created_at::date = now()::date;
  IF v_today_total + v_viewer > v_per_viewer_daily_cap THEN
    RAISE EXCEPTION 'per-viewer daily cap exceeded';
  END IF;

  -- Budget debit; flips to exhausted if fully spent. Guarded so we never overspend.
  UPDATE public.campaigns
    SET spent_usdc = spent_usdc + v_gross,
        status = CASE WHEN spent_usdc + v_gross >= total_budget_usdc
                      THEN 'exhausted' ELSE status END,
        updated_at = now()
    WHERE id = p_campaign_id
      AND spent_usdc + v_gross <= total_budget_usdc;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'budget exhausted';
  END IF;

  -- Insert the pending earning. The UNIQUE(composite_hash, viewer_wallet)
  -- constraint provides replay idempotency — a duplicate raises a unique
  -- violation that the API layer translates into 409 Conflict.
  INSERT INTO public.campaign_view_earnings
    (campaign_id, video_id, viewer_wallet, composite_hash, seconds,
     gross_usdc, viewer_usdc, creator_usdc, platform_usdc, status)
  VALUES (p_campaign_id, p_video_id, p_viewer, p_composite, p_seconds,
          v_gross, v_viewer, v_creator, v_platform, 'pending')
  RETURNING id INTO v_row;

  RETURN v_row;
END;
$$ LANGUAGE plpgsql;

-- Service role only — anon/authenticated must never call this directly.
REVOKE EXECUTE ON FUNCTION public.record_campaign_view_earning(uuid, int, text, text, int, numeric, int, int, int)
  FROM anon, authenticated;
```

---

## 3. API surface (Next.js route handlers)

All under `app/api/campaigns/`. Reuse `requireWalletAuthFor`, `checkBotIdDeep`, `rateLimiters`, `createServiceClient`, and zod-validated bodies (mirror `tipSchema` in `stickers/tips`).

### 3.1 `POST /api/campaigns` — sponsor creates a campaign
- Auth: `requireWalletAuthFor(req, body.sponsorWallet)`.
- Body: name, total_budget_usdc, cpv_rate, splits (bps), video ids, optional sticker_token_id/proposal_id.
- Inserts `campaigns` + `campaign_videos`.
- v1: the sponsor separately USDC-transfers `total_budget_usdc` to the platform hot wallet out-of-band (the route records the funding tx hash for reconciliation). v2: on-chain `CampaignEscrow.deposit` (§6).

### 3.2 `GET /api/campaigns/[id]` — public campaign metadata + remaining budget.

### 3.3 `GET /api/campaigns/for-video/[videoId]` — which campaigns sponsor this video (the extension calls this to know whether the current video is "earn-enabled").

### 3.4 `GET /api/campaigns/[id]/videos` — list sponsored videos for a campaign.

### 3.5 `POST /api/campaigns/access/[playbackId]` — **the sponsored-access resolver**
Counterpart to the existing `streams/access/[playbackId]`. Given a `playbackId` (+ viewer wallet auth headers), returns:
```ts
{
  sponsored: boolean,
  campaignId: string | null,
  requiresMetoken: false,          // always false when sponsored
  viewerCpvRatePerSec: number,    // what the viewer will earn (viewer_share * cpv_rate)
  compositeSeed: string,          // hash seed the extension uses for heartbeats
  playbackId, thumbnailUrl, streamName
}
```
Logic: look up `video_assets` by `playback_id` → join `campaign_videos` → find an `active`, in-window, budget-remaining campaign. If found, `sponsored=true` and the MeToken gate is bypassed for this viewer. If not, fall through to the existing `checkMeTokenAccess` path unchanged.

### 3.6 `POST /api/campaigns/heartbeat` — **proof-of-watch + earning record** (the core earn endpoint)
- Auth: `requireWalletAuthFor(req, body.viewerWallet)`.
- Guards: `checkBotIdDeep()` + `rateLimiters.strict` + **geo check** (below).
- Body (zod):
  ```ts
  {
    campaignId: string(uuid),
    videoId: number,
    viewerWallet: address,
    startTime: int, endTime: int,         // unix seconds; endTime-startTime <= MAX_HOLD_SECONDS (3600)
    compositeHash: string,                // buildCompositeHash(videoHash, campaignHash)
    heartbitMintReceipt?: object,         // result from /api/heartbit/mint (optional but recommended)
  }
  ```
- **Geo-restriction (earning only):** derive the viewer's region from the request (`request.geo` on Vercel, or `CF-IPCountry` / IP geo fallback). If the region is in the flagged-jurisdictions set, **return `202 EarnNotAvailable` — playback is fine, earning is declined.** The extension shows "Earning not available in your region" but keeps playing. Viewing is never blocked by this check; only earning. Store the region on the earning row for audit.
- Validation (mirrors `heartbit/mint/route.ts`):
  - `endTime > startTime`, `<= MAX_HOLD_SECONDS`, `|now - endTime| <= 120`.
  - `compositeHash` matches recomputed `videoHash|campaignHash` server-side.
  - **Idempotency:** the RPC's `UNIQUE (composite_hash, viewer_wallet)` → duplicate returns 409.
  - If `heartbitMintReceipt` provided, verify the HeartBit token id exists on-chain via `getTokenIdByHash(compositeHash)` (reuse `HeartBitClient`) — this is the strong, tamper-evident proof.
- **Rate model:** `viewer_usdc = seconds × cpv_rate` (flat CPV; default `0.01/s`). No completion bonus / tiering / cap-by-completion.
- Calls `record_campaign_view_earning()` → returns the pending earning id + amounts.
- A `409 Conflict` from the RPC (duplicate composite+viewer) is a **success-equivalent** ("already counted") — the extension should treat it as no-op rather than an error.

### 3.7 `GET /api/campaigns/earnings?wallet=...` — viewer's lifetime + pending earnings (the extension shows this).

### 3.8 Settlement (server-side, not client-triggered)
`app/api/campaigns/settle/route.ts` (cron-protected or internal) OR a Supabase Edge Function scheduled job:
- Select `status='pending'` rows batched.
- **Viewer payout:** pay `viewer_usdc` → `viewer_wallet` via the existing gasless USDC transfer path (Account Kit paymaster / `swap/execute`-style send). Record `viewer_payout_tx_hash`.
- **Creator payout (Splits.org):** if the video's `splits_address` is set, send `creator_usdc` to that Splits contract and call its `distribute()` so collaborators receive their `video_collaborators` bps shares automatically — zero new split logic. If `splits_address` is null, fall back to a direct transfer to `creator_id`. Record `creator_payout_tx_hash`.
- **Platform payout:** `platform_usdc` stays in the platform hot wallet (already debited from the campaign at record time) — no transfer needed; record it as realized platform revenue.
- Set `status='settled'`, `settled_at=now()`.
- Failures → `status='failed'` (retryable). Suspicious patterns (too-fast batches, headless UA from `checkBotIdDeep`, IP velocity, geo-mismatch between heartbeat region and wallet's known region) → `status='flagged'` for manual review; **flagged rows are not paid**.
- v2: settlement signs a `Payout` EIP-712 voucher (§6) instead of transferring directly, so viewers can redeem trust-minimally.

---

## 4. Embed & cross-platform earn (Bluesky / X / any site)

The repo already ships an embeddable player: `app/embed/discover/[id]/page.tsx` renders `components/Embed/DiscoverEmbedPlayer.tsx` (Livepeer playback + the existing MeToken gate). This section adds the **copy-paste embed code** under each video view, **platform-specific delivery**, and the **wallet-aware "special like" button** that lets viewers on other platforms contribute.

### 4.1 Embed code generator (under each video)

Each video view renders a "Embed" affordance that copies:
```html
<blockquote class="crtv-embed" data-video="<assetId>">
  <iframe
    src="https://crtv3.vercel.app/embed/discover/<assetId>"
    allow="autoplay; fullscreen; encrypted-media"
    allowfullscreen
    width="560" height="315"
    frameborder="0"></iframe>
</blockquote>
<script async src="https://crtv3.vercel.app/embed.js"></script>
```
`embed.js` is a tiny loader that post-processes `.crtv-embed` blocks (responsive sizing, optional click-to-play poster). The iframe stays **same-origin to crtv3**, which is what lets the extension (and in-embed wallet connect) work off-platform.

### 4.2 Platform delivery

| Platform | Mechanism | Interactive earn? |
|---|---|---|
| **Bluesky** | iframe embed (Bluesky renders arbitrary iframes in posts) | ✅ Yes — extension injects into the crtv3 iframe |
| **X / Twitter** | **oEmbed provider** (`GET /api/embed/oembed`) + `twitter:card=player` meta on the embed page; falls back to a share link with `og:video` MP4 poster. X does **not** render arbitrary iframes in the timeline, so in-timeline interactive earn needs the extension to detect crtv3 share links on x.com and inject its own player. | ⚠️ Share-link only without extension |
| **Any website / blog / Notion** | Copy-paste iframe above | ✅ Yes |

New routes for platform integration:
- `GET /api/embed/oembed?url=...&maxwidth=...` — oEmbed JSON provider (registered with Bluesky/X as a provider). Returns `html` (the iframe), `provider_name`, `thumbnail_url`.
- The embed page (`app/embed/discover/[id]`) sets `twitter:card=player`, `twitter:player` (the iframe URL), `og:video`, `og:image` (thumbnail) for rich previews where iframes aren't allowed.

### 4.3 The "special like" button (wallet-aware)

A single like button on the embed whose behavior depends on the viewer's connection state — this is what makes embedded viewers *contribute* with one tap:

| Viewer state | Tap does |
|---|---|
| No wallet connected | Anonymous like — increments `video_assets.likes_count` only. No payout. |
| Wallet connected **and** active campaign for this video | **Earn:** the campaign credits the viewer a small USDC engagement bonus (campaign → viewer). Reuse `record_campaign_view_earning()` with a one-shot "engagement" batch (e.g. a fixed `seconds` equivalent) instead of a watch batch. |
| Wallet connected, **no** campaign | **Tip:** micro-USDC from viewer → creator, the existing `sticker_tips` direction (viewer-pays), unchanged. |

So the same tap means earn / tip / like depending on context, and only wallet-connected viewers get the on-chain behavior. The button is the existing like UX; the wallet-awareness is invisible to the user.

### 4.4 Reaching the embed off-platform

- **Browser extension:** the content script declares `host_permissions: ["https://crtv3.vercel.app/*"]` and `all_frames: true`. It then injects into the crtv3 embed iframe **even when that iframe is embedded on bsky.app or any other site**, detects the `<video>`, and runs the exact heartbeat/earn logic from §5. No per-site host permissions needed because the iframe is same-origin to crtv3.
- **Mobile (no extension):** Chrome Android does not support arbitrary MV3 extensions; Safari iOS has limited support. So mobile earning should **not** depend on an extension. Instead the embed page itself offers **in-embed wallet connect** (Account Kit passkey/social, already in the repo) — the viewer taps connect inside the embed, then the special like / watch-earn works in-page. Desktop leans on the extension; mobile leans on in-embed connect.

### 4.5 Embed-specific anti-abuse

- **Hidden/zero-size embed farming:** content script and server reject the embed when the iframe `getBoundingClientRect()` area is below a threshold (e.g. < 200×150 px) — prevents invisible autoplay farming.
- **Autoplay-only:** reuse the §5.2 rule — credit only while visible, focused, `playbackRate === 1`, and after a real user `play` gesture; muted-but-never-engaged autoplay does not earn.
- **Same heartbeat/idempotency:** the embed uses the same `/api/campaigns/heartbeat` and `UNIQUE (composite_hash, viewer_wallet)` replay protection as in-page views — an embed view is just another verified batch.

### 4.6 Implementation notes (new files)

- `app/embed/[id]/page.tsx` — alias/extend the existing `embed/discover/[id]` route with `twitter:card` + oEmbed meta.
- `app/api/embed/oembed/route.ts` — oEmbed provider JSON.
- `components/Embed/EmbedShareButton.tsx` — the copy-paste snippet UI under each video (mirrors existing `components/vote/CampaignShareButton.tsx`).
- `components/Embed/SpecialLikeButton.tsx` — the wallet-aware like (branches on connection/campaign state).
- `public/embed.js` — the lightweight iframe loader.

## 5. Browser extension (MV3 Chrome + Firefox)

### 5.1 Shape
- `manifest.json` (MV3, `host_permissions` for `crtv3.vercel.app` / your domain).
- **Service worker** (+ offscreen document for crypto): manages wallet connection, signs `X-Wallet-*` auth headers via `viem` + `window.crypto.subtle` (or a popup signer using Account Kit's passkey/social flow for smart accounts).
- **Content script** injected into the player page: observes the `<video>` element, emits heartbeats on real playback (`timeupdate` + `playing` + visibility + tab-active checks), batches them, and POSTs to `/api/campaigns/heartbeat`.
- **Popup UI**: connect wallet, show current campaign, live earnings counter, daily-cap progress, settlement status.
- **Distribution:** **sideload developer-mode beta first** (unpacked load to a small group — fast iteration, no review delays), then publish to the **Chrome Web Store** under u9itus once the API + UI are stable (reach + auto-updates). Firefox/AMO is a later option since MV3 is largely portable.

### 5.2 Anti-fraud heartbeat rules (enforced in content script + re-checked server-side)
- Only count time while: `document.visibilityState === 'visible'` AND `!video.paused` AND `video.playbackRate === 1` AND tab focused AND actually playing (not muted-but-never-engaged autoplay — gate on a real `play` user gesture).
- Batch heartbeats every **N seconds** (e.g. 15s) or on pause/seek/unload; cap each batch at `MAX_HOLD_SECONDS` (3600).
- Compute `compositeHash = buildCompositeHash(videoHash, campaignHash)` where `videoHash` is derived from the asset id + the campaign-issued `compositeSeed` (returned by `/api/campaigns/access/[playbackId]`). This binds the proof to the specific campaign-video pair and is what makes the `UNIQUE (composite_hash, viewer_wallet)` idempotency key work.
- Send `startTime/endTime` aligned to real playback positions; reject if `endTime - startTime` exceeds wall-clock elapsed (impossible-to-watch-fast protection; server also caps via HeartBit's `|now - endTime| <= 120`).

### 5.3 Wallet connection
- **Option A (EOA):** extension holds a private key in `chrome.storage` (encrypted) or uses `window.ethereum` if a wallet extension is present. Sign the canonical message from `buildWalletAuthMessage`.
- **Option B (smart account — recommended):** reuse Alchemy Account Kit with passkeys so the viewer has no seed phrase; the extension gets a smart-account address and signs via EIP-1271, which `require-wallet` already verifies. USDC payouts land in the smart account gaslessly via the existing paymaster.

### 5.4 Auth header helper (mirrors `signWalletAuthHeaders`)
The extension produces the same three headers the web app already sends:
```
X-Wallet-Address: 0x...
X-Wallet-Timestamp: <unix seconds, must be <= 5 min old>
X-Wallet-Signature: <sign("Authorize Creative TV request for address {addr} at {ts}")>
```
No server auth changes needed.

---

## 6. On-chain campaign escrow (v2 — out of scope for v1)

> v1 runs the budget off-chain in the `campaigns` table with a platform hot wallet funding settlements directly. This section documents the **trust-minimized upgrade** for when you want sponsors to deposit USDC on-chain and viewers to redeem signed payout vouchers. It mirrors `CampaignStickers`'s EIP-712 voucher pattern (`CLAIM_TYPEHASH` → `PAYOUT_TYPEHASH`) so codebase conventions stay consistent.

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/utils/cryptography/EIP712.sol";
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";

/// @notice v2: holds USDC a sponsor pre-pays for a campaign; pays out per signed voucher.
contract CampaignEscrow is EIP712 {
    using ECDSA for bytes32;
    IERC20 public immutable usdc;
    bytes32 public constant PAYOUT_TYPEHASH =
        keccak256("Payout(bytes32 campaignId,address viewer,uint256 amount,uint256 nonce)");

    mapping(bytes32 => uint256) public deposited;     // campaignId => USDC
    mapping(bytes32 => uint256) public withdrawn;     // campaignId => USDC
    mapping(address => mapping(uint256 => bool)) public usedNonce; // viewer => nonce => used
    address public platform;                          // signs payout vouchers (server-side key)

    constructor(address usdc_, address platform_) EIP712("CampaignEscrow","1") {
        usdc = IERC20(usdc_);
        platform = platform_;
    }

    function deposit(bytes32 campaignId, uint256 amount) external {
        usdc.transferFrom(msg.sender, address(this), amount);
        deposited[campaignId] += amount;
    }

    /// @notice Viewer (or relayer) redeems a platform-signed payout voucher.
    /// Gasless for the viewer if the platform relayer submits the tx; the
    /// viewer's authority is in the voucher signature, not the tx signer.
    function claimPayout(bytes32 campaignId, address viewer, uint256 amount,
                         uint256 nonce, bytes calldata sig) external {
        require(!usedNonce[viewer][nonce], "used");
        bytes32 digest = _hashTypedDataV4(
            keccak256(abi.encode(PAYOUT_TYPEHASH, campaignId, viewer, amount, nonce)));
        require(digest.recover(sig) == platform, "bad sig");
        require(withdrawn[campaignId] + amount <= deposited[campaignId], "overdraw");
        usedNonce[viewer][nonce] = true;
        withdrawn[campaignId] += amount;
        usdc.transfer(viewer, amount);
    }
}
```

The platform (u9itus) signs payouts server-side during settlement (§3.8); a relayer submits them gasless, or the viewer's smart account pays gas via the existing paymaster.

---

## 7. Security & anti-abuse checklist

- **Replay:** `UNIQUE (composite_hash, viewer_wallet)` + HeartBit `|now - endTime| <= 120` window + 5-min auth signature window.
- **Double-spend budget:** `record_campaign_view_earning()` runs the debit under `FOR UPDATE`; concurrent heartbeats serialize. Budget exhaustion flips status atomically.
- **Headless/farm:** `checkBotIdDeep` on every mutating endpoint; flag (don't pay) on suspicious UA/IP velocity; per-viewer daily cap.
- **Watch-speed cheating:** server caps `seconds <= MAX_HOLD_SECONDS` per batch and `|now - endTime| <= 120`; content script refuses `playbackRate != 1`.
- **Visibility cheating:** content script only credits visible, focused, actually-playing tabs; server doesn't trust client alone — HeartBit on-chain mint receipt is the strong proof when provided.
- **Wallet spoofing:** `requireWalletAuthFor` EIP-1271 checks the viewer actually controls the earning address.
- **Sponsor key leak:** sponsor wallet only signs the campaign-creation flow; payouts are signed by the *platform* key (kept server-side, like the existing `STICKER_VERIFIER_PRIVATE_KEY`).
- **Geo / money-transmitter risk:** earning is blocked (not viewing) in flagged jurisdictions via the heartbeat geo check; geo-mismatch between heartbeat region and the wallet's known region during settlement flags the row for review.
- **RLS:** public read, service-role writes — identical to `campaign_stickers`, so client SDKs can read campaign/earnings but never write them.

---

## 8. Implementation order (incremental, each step ships value)

1. **Migrations + types** — `campaigns`, `campaign_videos`, `campaign_view_earnings`, `record_campaign_view_earning()` RPC. Add `lib/types/campaign.ts`.
2. **Campaign CRUD** — `POST/GET /api/campaigns`, `/for-video`, `/[id]/videos` (reuse auth + zod patterns).
2b. **Programmatic upload (optional)** — `POST /api/campaigns/upload` (wallet-authenticated upload → Grove + Livepeer → `video_assets` + `campaign_videos` + optional Story IP registration).
3. **Sponsored access resolver** — `POST /api/campaigns/access/[playbackId]`; wire the player to prefer the sponsored gate before the MeToken gate.
4. **Heartbeat + earning** — `POST /api/campaigns/heartbeat` with full proof validation + idempotency; `GET /api/campaigns/earnings`.
5. **Settlement job** — pending → settled via existing gasless USDC transfer; creator split to `splits_address` when present.
6. **Extension MVP** — MV3 content script (heartbeats), popup (connect + earnings), auth-header signer. EOA first, smart-account second.
7. **Embed & cross-platform** — `embed.js` loader + `EmbedShareButton`, `oembed` provider route + `twitter:card`/`og:video` meta on the embed page, `SpecialLikeButton` (wallet-aware earn/tip/like branch), iframe-size anti-farm guard.
8. **(v2) On-chain escrow** — `CampaignEscrow.sol` + subgraph entity; switch settlement to signed-voucher claims.
9. **Analytics** — campaign dashboard: spend velocity, unique earners, watch-time distribution, fraud-flag rate.

---

## 9. Open decisions — all resolved

All previously-open decisions are now locked in (see the **Resolved decisions** table at the top). For convenience:

1. **Creator split routing** → **Reuse Splits.org** (`video_assets.splits_address` → `distribute()`; fallback direct to `creator_id` if unset). Implemented in §3.8.
2. **Rate model** → **Flat CPV per second** (`seconds × cpv_rate`, default `0.01/s`). Implemented in §3.6.
3. **Extension distribution** → **Sideload developer-mode beta first, then Chrome Web Store** under u9itus once stable. Noted in §5.1.
4. **Geo / regulatory** → **Geo-restrict earning** in flagged jurisdictions (viewing unaffected). Implemented in §3.6 + §7.

No open decisions remain; the spec is implementation-ready.