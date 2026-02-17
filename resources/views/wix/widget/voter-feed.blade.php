<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>U9itus – Watch & Earn</title>
    <style>
        :root {
            --primary: #3899EC;
            --success: #60BC57;
            --danger: #EE5951;
            --warning: #FAC249;
            --bg: #0D1117;
            --card: #161B22;
            --text: #F0F6FC;
            --text-light: #8B949E;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .player-container {
            max-width: 720px;
            width: 100%;
        }
        .video-wrapper {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 16/9;
        }
        .video-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .video-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.6);
            z-index: 10;
        }
        .video-overlay.hidden { display: none; }
        .play-btn {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: var(--primary);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .play-btn svg { fill: #fff; width: 36px; height: 36px; }

        .progress-bar-container {
            margin-top: 12px;
            background: #30363D;
            border-radius: 6px;
            height: 8px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--success));
            width: 0%;
            transition: width 0.3s;
            border-radius: 6px;
        }

        .info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding: 16px;
            background: var(--card);
            border-radius: 12px;
        }
        .payout-display {
            font-size: 28px;
            font-weight: 800;
            color: var(--success);
        }
        .timer {
            font-size: 18px;
            color: var(--text-light);
            font-variant-numeric: tabular-nums;
        }

        .status-message {
            text-align: center;
            margin-top: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
        }
        .status-success { background: rgba(96,188,87,0.15); color: var(--success); }
        .status-warning { background: rgba(250,194,73,0.15); color: var(--warning); }

        .campaign-info {
            margin-top: 16px;
            padding: 16px;
            background: var(--card);
            border-radius: 12px;
        }
        .campaign-info h3 { font-size: 18px; margin-bottom: 8px; }
        .campaign-info p { color: var(--text-light); font-size: 14px; line-height: 1.6; }

        .warning-text {
            color: var(--warning);
            font-size: 12px;
            text-align: center;
            margin-top: 12px;
        }
    </style>
</head>
<body>

<div class="player-container">
    {{-- Video Player --}}
    <div class="video-wrapper">
        <video id="videoPlayer" preload="metadata">
            <source src="" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <div class="video-overlay" id="playOverlay">
            <button class="play-btn" onclick="startWatching()">
                <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>
        </div>
    </div>

    {{-- Progress --}}
    <div class="progress-bar-container">
        <div class="progress-bar" id="progressBar"></div>
    </div>

    {{-- Info --}}
    <div class="info-bar">
        <div>
            <span class="payout-display" id="payoutAmount">$0.25</span>
            <div style="font-size:12px; color:var(--text-light); margin-top:2px;">Earn for watching</div>
        </div>
        <div class="timer" id="timerDisplay">0:00 / 0:00</div>
    </div>

    {{-- Campaign Info --}}
    <div class="campaign-info" id="campaignInfo">
        <h3 id="campaignTitle">Political Message</h3>
        <p id="campaignSummary">Watch the full message to earn your payout.</p>
    </div>

    {{-- Status --}}
    <div id="statusMessage" style="display:none;"></div>

    <p class="warning-text">
        You must watch the entire message to qualify for payment.
        Pausing or skipping will disqualify the view.
    </p>
</div>

<script>
    const API_BASE = '{{ url("/api/v1") }}';
    const CSRF = '{{ csrf_token() }}';
    const HOST_ORIGIN = document.referrer ? new URL(document.referrer).origin : '*';

    // Parse query params - server will provide session data
    const params = new URLSearchParams(window.location.search);
    const campaignUuid = params.get('campaign');
    const politicianUuid = params.get('politician');
    const voterEmail = params.get('voter_email');
    
    let sessionUuid = null;
    let mediaUrl = null;
    let duration = 60;
    let payout = 0.25;
    let campaignTitle = 'Political Message';
    let campaignSummary = 'Watch the full message to earn your payout.';

    const video = document.getElementById('videoPlayer');
    const progressBar = document.getElementById('progressBar');
    const timerDisplay = document.getElementById('timerDisplay');
    const payoutDisplay = document.getElementById('payoutAmount');
    const playOverlay = document.getElementById('playOverlay');
    const statusMessage = document.getElementById('statusMessage');
    const campaignTitleEl = document.getElementById('campaignTitle');
    const campaignSummaryEl = document.getElementById('campaignSummary');

    let heartbeatInterval = null;
    let secondsWatched = 0;
    let viewCompleted = false;
    let voterUuid = null;

    // Initialize: get or create voter, then fetch campaign and create session
    async function initialize() {
        try {
            // Step 1: Get or create anonymous voter
            voterUuid = localStorage.getItem('u9itus_voter_uuid');
            if (!voterUuid && voterEmail) {
                // Create voter with email if provided
                const res = await fetch(`${API_BASE}/voters`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ 
                        email: voterEmail,
                        full_name: 'Anonymous Voter',
                        zip_code: '00000'
                    }),
                });
                const data = await res.json();
                voterUuid = data.voter.uuid;
                localStorage.setItem('u9itus_voter_uuid', voterUuid);
            } else if (!voterUuid) {
                // Create anonymous voter
                const res = await fetch(`${API_BASE}/voters`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ 
                        email: `anon-${Date.now()}@temp.u9itus.dev`,
                        full_name: 'Anonymous Voter',
                        zip_code: '00000'
                    }),
                });
                const data = await res.json();
                voterUuid = data.voter.uuid;
                localStorage.setItem('u9itus_voter_uuid', voterUuid);
            }

            // Step 2: Get available campaigns or specific campaign
            let targetCampaignUuid = campaignUuid;
            if (!targetCampaignUuid && politicianUuid) {
                // Fetch campaigns for this politician (would need a new endpoint)
                // For now, fall back to available campaigns
                const res = await fetch(`${API_BASE}/voters/${voterUuid}/campaigns`);
                const data = await res.json();
                if (data.campaigns && data.campaigns.length > 0) {
                    targetCampaignUuid = data.campaigns[0].uuid;
                    campaignTitle = data.campaigns[0].title;
                    campaignSummary = data.campaigns[0].message_summary;
                    payout = data.campaigns[0].payout;
                    duration = data.campaigns[0].media_duration || 60;
                }
            } else if (!targetCampaignUuid) {
                // No campaign specified - fetch first available
                const res = await fetch(`${API_BASE}/voters/${voterUuid}/campaigns`);
                const data = await res.json();
                if (data.campaigns && data.campaigns.length > 0) {
                    targetCampaignUuid = data.campaigns[0].uuid;
                    campaignTitle = data.campaigns[0].title;
                    campaignSummary = data.campaigns[0].message_summary;
                    payout = data.campaigns[0].payout;
                    duration = data.campaigns[0].media_duration || 60;
                }
            }

            if (!targetCampaignUuid) {
                showError('No campaigns available at this time.');
                return;
            }

            // Step 3: Start the view session
            const res = await fetch(`${API_BASE}/voters/${voterUuid}/campaigns/${targetCampaignUuid}/watch`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({}),
            });
            
            if (!res.ok) {
                const err = await res.json();
                showError(err.error || 'Unable to start session');
                return;
            }

            const sessionData = await res.json();
            sessionUuid = sessionData.session_id;
            mediaUrl = sessionData.media_url;
            payout = sessionData.payout;
            duration = sessionData.duration;

            // Update UI
            video.querySelector('source').src = mediaUrl;
            video.load();
            payoutDisplay.textContent = `$${payout.toFixed(2)}`;
            timerDisplay.textContent = `0:00 / ${formatTime(duration)}`;
            campaignTitleEl.textContent = campaignTitle;
            campaignSummaryEl.textContent = campaignSummary;

            // Notify host that we're ready and send initial size
            postMessageToHost('widget:resize', { height: document.body.scrollHeight });
            postMessageToHost('widget:ready', { 
                campaign: targetCampaignUuid, 
                session: sessionUuid 
            });

        } catch (e) {
            console.error('Initialization failed', e);
            showError('Failed to load video player. Please try again.');
        }
    }

    function showError(message) {
        statusMessage.style.display = 'block';
        statusMessage.className = 'status-message status-warning';
        statusMessage.textContent = message;
        playOverlay.style.display = 'none';
    }

    // PostMessage helper to communicate with Wix host
    function postMessageToHost(type, payload) {
        try {
            window.parent.postMessage({ type, payload }, HOST_ORIGIN);
        } catch (e) {
            console.warn('postMessage failed', e);
        }
    }

    // Listen for messages from host
    window.addEventListener('message', (event) => {
        const msg = event.data || {};
        if (msg.type === 'host:requestSize') {
            postMessageToHost('widget:resize', { height: document.body.scrollHeight });
        }
    });

    // Prevent seeking
    video.addEventListener('seeking', (e) => {
        if (!viewCompleted) {
            video.currentTime = secondsWatched;
        }
    });

    // Track progress
    video.addEventListener('timeupdate', () => {
        secondsWatched = Math.floor(video.currentTime);
        const pct = (secondsWatched / duration) * 100;
        progressBar.style.width = `${Math.min(pct, 100)}%`;
        timerDisplay.textContent = `${formatTime(secondsWatched)} / ${formatTime(duration)}`;
    });

    video.addEventListener('ended', () => {
        completeView();
    });

    function startWatching() {
        if (!sessionUuid) {
            showError('Session not ready. Please refresh the page.');
            return;
        }
        
        playOverlay.classList.add('hidden');
        video.play();

        // Heartbeat every 5 seconds - send via postMessage to host who will forward to API
        heartbeatInterval = setInterval(() => {
            postMessageToHost('view:heartbeat', { 
                session: sessionUuid, 
                secondsWatched: secondsWatched 
            });
        }, 5000);
    }

    async function completeView() {
        viewCompleted = true;
        clearInterval(heartbeatInterval);

        if (!sessionUuid) return;

        // Send complete event via postMessage to host
        postMessageToHost('view:complete', {
            session: sessionUuid,
            totalSecondsWatched: secondsWatched
        });

        // Also call API directly as backup
        try {
            const res = await fetch(`${API_BASE}/sessions/${sessionUuid}/complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ total_seconds_watched: secondsWatched }),
            });
            const json = await res.json();

            statusMessage.style.display = 'block';
            if (json.payout_earned > 0) {
                statusMessage.className = 'status-message status-success';
                statusMessage.textContent = `You earned $${parseFloat(json.payout_earned).toFixed(2)}! Thank you for watching.`;
            } else {
                statusMessage.className = 'status-message status-warning';
                statusMessage.textContent = 'View not qualified for payout. Please watch the full message.';
            }
        } catch (e) {
            console.error('Complete view failed', e);
        }
    }

    function formatTime(sec) {
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    // Initialize on load
    initialize();
</script>
</body>
</html>
