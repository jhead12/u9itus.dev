<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Earn Money Watching Political Campaigns — U9itus</title>
    <meta name="description" content="Get paid $0.50 every time you watch a political campaign video on U9itus. Free to join. No experience needed.">
    <link rel="canonical" href="{{ url('/earn') }}">
    <meta property="og:title"       content="Earn Money Watching Political Campaigns — U9itus">
    <meta property="og:description" content="Get paid $0.50 every time you watch a political campaign video. Free to join.">
    <meta property="og:url"         content="{{ url('/earn') }}">
    <meta property="og:type"        content="website">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet"/>

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        :root { font-family: 'Inter', system-ui, sans-serif; }
        body  { background: #06091a; color: #e2e8f0; margin: 0; }

        /* ── Hero gradient ── */
        .hero-grad {
            background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99,102,241,0.25), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(16,185,129,0.10), transparent),
                        #06091a;
        }

        /* ── Step cards ── */
        .step-card {
            background: rgba(15,23,42,0.80);
            border: 1px solid rgba(99,102,241,0.18);
            border-radius: 18px;
            padding: 32px 28px;
            transition: border-color .2s, transform .2s;
        }
        .step-card:hover {
            border-color: rgba(99,102,241,0.45);
            transform: translateY(-2px);
        }
        .step-num {
            width: 44px; height: 44px; border-radius: 50%;
            background: rgba(99,102,241,0.15);
            border: 1.5px solid rgba(99,102,241,0.4);
            color: #a5b4fc; font-size: 18px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px; flex-shrink: 0;
        }

        /* ── Payout badge ── */
        .payout-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.35);
            border-radius: 999px;
            padding: 6px 18px; font-size: 15px; font-weight: 700;
            color: #34d399;
        }

        /* ── CTAs ── */
        .btn-primary {
            display: inline-flex; align-items: center; gap-: 8px;
            background: #6366f1; color: #fff;
            font-weight: 700; font-size: 16px;
            padding: 14px 32px; border-radius: 12px;
            text-decoration: none;
            transition: background .15s, transform .15s;
        }
        .btn-primary:hover { background: #818cf8; transform: translateY(-1px); }
        .btn-secondary {
            display: inline-flex; align-items: center;
            color: #94a3b8; font-size: 14px; font-weight: 500;
            text-decoration: underline; underline-offset: 3px;
            transition: color .15s;
        }
        .btn-secondary:hover { color: #e2e8f0; }

        /* ── FAQ ── */
        details { border-bottom: 1px solid rgba(99,102,241,0.12); }
        details summary {
            cursor: pointer; padding: 18px 0;
            font-weight: 600; font-size: 15px; color: #cbd5e1;
            list-style: none; display: flex; justify-content: space-between; align-items: center;
        }
        details summary::-webkit-details-marker { display: none; }
        details summary::after {
            content: '+'; font-size: 20px; color: #6366f1;
            transition: transform .2s; font-weight: 300;
        }
        details[open] summary::after { content: '−'; }
        details p { color: #94a3b8; font-size: 14px; line-height: 1.75; padding-bottom: 18px; margin: 0; }
    </style>
</head>
<body>

{{-- ── Top nav ── --}}
<nav style="position:sticky;top:0;z-index:50;background:rgba(6,9,26,0.9);backdrop-filter:blur(12px);border-bottom:1px solid rgba(99,102,241,0.12);">
    <div style="max-width:1100px;margin:0 auto;padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;">
        <a href="{{ url('/') }}" style="color:#818cf8;font-weight:800;font-size:20px;text-decoration:none;">U9itus</a>
        <div style="display:flex;gap:12px;align-items:center;">
            <a href="{{ url('/map') }}" style="color:#64748b;font-size:14px;text-decoration:none;font-weight:500;">← Back to Map</a>
            <a href="{{ route('login') }}" style="color:#94a3b8;font-size:14px;text-decoration:none;font-weight:500;">Sign in</a>
            <a href="{{ route('register.voter') }}{{ request()->query('ref') ? '?ref='.request()->query('ref') : '' }}"
               style="background:#6366f1;color:#fff;font-size:13px;font-weight:700;padding:7px 18px;border-radius:8px;text-decoration:none;">
                Get Started
            </a>
        </div>
    </div>
</nav>

{{-- ── Hero ── --}}
<section class="hero-grad" style="padding:80px 24px 72px;text-align:center;">
    <div style="max-width:720px;margin:0 auto;">
        <div class="payout-badge" style="margin-bottom:28px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            Earn up to $0.50 per video — paid to you
        </div>

        <h1 style="font-size:clamp(32px,6vw,56px);font-weight:800;line-height:1.12;margin:0 0 20px;color:#f1f5f9;">
            Get paid to stay<br>
            <span style="background:linear-gradient(90deg,#818cf8,#34d399);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">politically informed</span>
        </h1>

        <p style="font-size:18px;color:#94a3b8;line-height:1.65;margin:0 0 36px;max-width:560px;margin-left:auto;margin-right:auto;">
            U9itus pays you every time you watch a political campaign video.
            No surveys, no points — real money deposited to your account.
        </p>

        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:16px;">
            <a href="{{ route('register.voter') }}{{ request()->query('ref') ? '?ref='.request()->query('ref') : '' }}"
               class="btn-primary">
                Create Free Account &rarr;
            </a>
            <a href="{{ url('/map') }}" class="btn-secondary">Explore the map first</a>
        </div>
        <p style="font-size:12px;color:#475569;margin:0;">Free to join · No credit card · Payouts via Stripe, PayPal, or Cash App</p>
    </div>
</section>

{{-- ── How it works ── --}}
<section style="padding:72px 24px;max-width:1100px;margin:0 auto;">
    <h2 style="text-align:center;font-size:28px;font-weight:700;margin:0 0 12px;color:#f1f5f9;">How it works</h2>
    <p style="text-align:center;color:#64748b;font-size:15px;margin:0 0 48px;">Three steps. No experience needed.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
        {{-- Step 1 --}}
        <div class="step-card">
            <div class="step-num">1</div>
            <h3 style="font-size:18px;font-weight:700;color:#e2e8f0;margin:0 0 10px;">Create a free account</h3>
            <p style="font-size:14px;color:#94a3b8;line-height:1.7;margin:0;">
                Sign up as a Citizen voter in under two minutes. We only ask for your name, email, and state so we can match you with campaigns in your area.
            </p>
        </div>

        {{-- Step 2 --}}
        <div class="step-card">
            <div class="step-num">2</div>
            <h3 style="font-size:18px;font-weight:700;color:#e2e8f0;margin:0 0 10px;">Watch campaign videos on the map</h3>
            <p style="font-size:14px;color:#94a3b8;line-height:1.7;margin:0;">
                Click any state on the U9itus interactive map, browse available campaign videos from politicians in that area, and press play. Watch to the end to earn.
            </p>
        </div>

        {{-- Step 3 --}}
        <div class="step-card">
            <div class="step-num">3</div>
            <h3 style="font-size:18px;font-weight:700;color:#e2e8f0;margin:0 0 10px;">Get paid — your way</h3>
            <p style="font-size:14px;color:#94a3b8;line-height:1.7;margin:0;">
                Your earnings accumulate in your wallet. Cash out anytime via Stripe, PayPal, or Cash App. Each qualifying view pays <strong style="color:#34d399;">up to $0.50</strong> directly to you.
            </p>
        </div>
    </div>
</section>

{{-- ── Earnings breakdown ── --}}
<section style="padding:0 24px 72px;max-width:1100px;margin:0 auto;">
    <div style="background:rgba(15,23,42,0.8);border:1px solid rgba(99,102,241,0.18);border-radius:18px;padding:40px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:32px;text-align:center;">
        <div>
            <div style="font-size:42px;font-weight:800;color:#34d399;">$0.50</div>
            <div style="font-size:13px;color:#64748b;margin-top:6px;">per qualifying view<br>(paid to you)</div>
        </div>
        <div>
            <div style="font-size:42px;font-weight:800;color:#818cf8;">$0.05</div>
            <div style="font-size:13px;color:#64748b;margin-top:6px;">referral commission<br>per friend's view</div>
        </div>
        <div>
            <div style="font-size:42px;font-weight:800;color:#f59e0b;">5 min</div>
            <div style="font-size:13px;color:#64748b;margin-top:6px;">typical video length<br>per campaign</div>
        </div>
        <div>
            <div style="font-size:42px;font-weight:800;color:#e2e8f0;">$0</div>
            <div style="font-size:13px;color:#64748b;margin-top:6px;">cost to join<br>ever</div>
        </div>
    </div>
</section>

{{-- ── Bonus: Referrals ── --}}
<section style="padding:0 24px 72px;max-width:1100px;margin:0 auto;">
    <div style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(16,185,129,0.08));border:1px solid rgba(99,102,241,0.22);border-radius:18px;padding:40px 36px;display:flex;gap:32px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:260px;">
            <p style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6366f1;margin:0 0 10px;">Bonus income</p>
            <h3 style="font-size:22px;font-weight:700;color:#f1f5f9;margin:0 0 12px;">Earn more by sharing the map</h3>
            <p style="font-size:14px;color:#94a3b8;line-height:1.7;margin:0;">
                Every U9itus account comes with a personal referral link. When you share a candidate's profile or the map and someone signs up through your link, you earn <strong style="color:#34d399;">$0.05 on every view they watch</strong> — automatically, for as long as they're active.
            </p>
        </div>
        <div style="text-align:center;flex-shrink:0;">
            <div style="font-size:52px;font-weight:800;color:#34d399;line-height:1;">+$0.05</div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;">per referred view</div>
        </div>
    </div>
</section>

{{-- ── FAQ ── --}}
<section style="padding:0 24px 80px;max-width:680px;margin:0 auto;">
    <h2 style="font-size:24px;font-weight:700;color:#f1f5f9;margin:0 0 32px;text-align:center;">Common questions</h2>

    <details>
        <summary>Who can join? <span></span></summary>
        <p>Any U.S. resident 18 or older can register as a Citizen voter. You don't need to be registered to vote with the government — just with U9itus.</p>
    </details>
    <details>
        <summary>How do I actually get the money? <span></span></summary>
        <p>Earnings build up in your U9itus wallet. Once you reach the minimum payout threshold you can withdraw to Stripe (debit card), PayPal, or Cash App. Payouts are processed weekly.</p>
    </details>
    <details>
        <summary>Is there a limit to how many videos I can watch? <span></span></summary>
        <p>There's a daily view limit to ensure fair distribution across all voters. You'll see your remaining views for the day in your dashboard.</p>
    </details>
    <details>
        <summary>Do I have to watch the whole video? <span></span></summary>
        <p>Yes — each campaign sets a minimum watch percentage (typically 80–100%). Your payout is credited only after you reach that threshold, so make sure you watch to the end.</p>
    </details>
    <details>
        <summary>What kinds of videos are on the map? <span></span></summary>
        <p>Campaign videos, position statements, and live Q&amp;A sessions uploaded by politicians who have registered on U9itus. All content is reviewed before going live. You can browse without an account to see what's available in your state.</p>
    </details>
    <details>
        <summary>Is my personal information safe? <span></span></summary>
        <p>We collect only what's needed to match you with relevant campaigns and process payouts. We never sell your data. See our <a href="{{ url('/privacy') }}" style="color:#818cf8;">Privacy Policy</a> for full details.</p>
    </details>
</section>

{{-- ── Bottom CTA ── --}}
<section style="padding:60px 24px 80px;text-align:center;border-top:1px solid rgba(99,102,241,0.10);">
    <h2 style="font-size:28px;font-weight:800;color:#f1f5f9;margin:0 0 14px;">Ready to start earning?</h2>
    <p style="color:#64748b;font-size:15px;margin:0 0 32px;">Join thousands of citizens already getting paid to stay informed.</p>
    <a href="{{ route('register.voter') }}{{ request()->query('ref') ? '?ref='.request()->query('ref') : '' }}"
       class="btn-primary" style="font-size:17px;padding:16px 40px;">
        Create Your Free Account &rarr;
    </a>
    <p style="margin-top:18px;font-size:13px;color:#334155;">
        Already have an account? <a href="{{ route('login') }}" style="color:#818cf8;">Sign in</a>
    </p>
</section>

</body>
</html>
