@extends('wix.layouts.dashboard')

@section('title', 'Voter Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1>Voter Dashboard</h1>
        <p class="subtitle">Watch political messages, earn money, refer friends</p>
    </div>
</div>

{{-- Earnings Overview --}}
<div class="wix-card" id="earnings-card">
    <h2>Your Earnings</h2>
    <div class="wix-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="wix-stat">
            <div class="value" id="stat-earned">$0.00</div>
            <div class="label">Total Earned</div>
        </div>
        <div class="wix-stat">
            <div class="value" id="stat-pending" style="color: var(--wix-color-warning);">$0.00</div>
            <div class="label">Pending</div>
        </div>
        <div class="wix-stat">
            <div class="value" id="stat-views">0</div>
            <div class="label">Views Completed</div>
        </div>
        <div class="wix-stat">
            <div class="value" id="stat-referral-earnings" style="color: var(--wix-color-success);">$0.00</div>
            <div class="label">Referral Earnings</div>
        </div>
    </div>
</div>

{{-- Referral Section --}}
<div class="wix-card">
    <h2>Refer &amp; Earn</h2>
    <p style="color: var(--wix-color-text-light); margin-bottom: 12px;">
        Share your referral code with friends. You earn <strong>$0.025</strong> (10% of $0.25)
        every time a voter you referred completes a view.
    </p>
    <div style="display:flex; gap:12px; align-items:center;">
        <input type="text" id="referral-code" readonly value="Loading..." style="padding:8px 16px; border:2px solid var(--wix-color-primary); border-radius:var(--wix-radius); font-size:16px; font-weight:700; letter-spacing:2px; width:200px; text-align:center;">
        <button class="wix-btn wix-btn-primary" onclick="copyReferralCode()">Copy Code</button>
    </div>
    <p id="referral-stats" style="margin-top:12px; font-size:13px; color: var(--wix-color-text-light);"></p>
</div>

{{-- Available Campaigns --}}
<div class="wix-card">
    <h2>Available Political Messages</h2>
    <p style="color: var(--wix-color-text-light); margin-bottom: 16px;">
        Watch the full message to earn <strong>$0.25 per view</strong>.
    </p>
    <div id="campaigns-feed">
        <p style="color:var(--wix-color-text-light);">Loading...</p>
    </div>
</div>

{{-- View History --}}
<div class="wix-card">
    <h2>View History</h2>
    <div id="view-history">
        <p style="color:var(--wix-color-text-light);">Loading...</p>
    </div>
</div>

@push('scripts')
<script>
    const API = window.D4D.apiBase;
    let voterId = null;

    // Check localStorage for voter ID, or show registration
    document.addEventListener('DOMContentLoaded', () => {
        voterId = localStorage.getItem('d4d_voter_id');
        if (voterId) {
            loadVoterData();
        } else {
            showRegistration();
        }
    });

    function showRegistration() {
        document.getElementById('earnings-card').innerHTML = `
            <h2>Register as a Voter</h2>
            <p style="color:var(--wix-color-text-light); margin-bottom:16px;">
                Create your profile to start watching political messages and earning money.
            </p>
            <form onsubmit="registerVoter(event)">
                <div class="wix-grid" style="grid-template-columns:1fr 1fr;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:13px;color:var(--wix-color-text-light);margin-bottom:4px;">Full Name</label>
                        <input type="text" name="full_name" required style="width:100%;padding:8px 12px;border:1px solid #C1E4FE;border-radius:var(--wix-radius);font-size:14px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:13px;color:var(--wix-color-text-light);margin-bottom:4px;">Email</label>
                        <input type="email" name="email" required style="width:100%;padding:8px 12px;border:1px solid #C1E4FE;border-radius:var(--wix-radius);font-size:14px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:13px;color:var(--wix-color-text-light);margin-bottom:4px;">State</label>
                        <input type="text" name="state" maxlength="2" placeholder="e.g. CA" style="width:100%;padding:8px 12px;border:1px solid #C1E4FE;border-radius:var(--wix-radius);font-size:14px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:13px;color:var(--wix-color-text-light);margin-bottom:4px;">Referral Code (optional)</label>
                        <input type="text" name="referral_code" maxlength="16" style="width:100%;padding:8px 12px;border:1px solid #C1E4FE;border-radius:var(--wix-radius);font-size:14px;">
                    </div>
                </div>
                <button type="submit" class="wix-btn wix-btn-success">Register & Start Earning</button>
            </form>
        `;
    }

    async function registerVoter(e) {
        e.preventDefault();
        const form = new FormData(e.target);
        const data = Object.fromEntries(form);

        const res = await fetch(`${API}/voters`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
            body: JSON.stringify(data),
        });
        const json = await res.json();
        if (json.voter) {
            voterId = json.voter.id;
            localStorage.setItem('d4d_voter_id', voterId);
            location.reload();
        } else {
            alert(json.message || 'Registration failed');
        }
    }

    async function loadVoterData() {
        // Profile & earnings
        const profileRes = await fetch(`${API}/voters/${voterId}`);
        const profile = await profileRes.json();

        if (profile.voter) {
            document.getElementById('stat-earned').textContent = `$${parseFloat(profile.earnings.total_earned).toFixed(2)}`;
            document.getElementById('stat-pending').textContent = `$${parseFloat(profile.earnings.pending_earnings).toFixed(2)}`;
            document.getElementById('stat-views').textContent = profile.earnings.total_views;
            document.getElementById('stat-referral-earnings').textContent = `$${parseFloat(profile.earnings.referral_earnings).toFixed(2)}`;
            document.getElementById('referral-code').value = profile.voter.referral_code;
            document.getElementById('referral-stats').textContent = `${profile.earnings.referrals_count} voters referred`;
        }

        // Available campaigns
        const campaignsRes = await fetch(`${API}/voters/${voterId}/campaigns`);
        const campaigns = await campaignsRes.json();
        renderCampaigns(campaigns.campaigns || []);

        // View history
        const historyRes = await fetch(`${API}/voters/${voterId}/history`);
        const history = await historyRes.json();
        renderHistory(history.data || []);
    }

    function renderCampaigns(campaigns) {
        const container = document.getElementById('campaigns-feed');
        if (campaigns.length === 0) {
            container.innerHTML = '<p style="color:var(--wix-color-text-light);">No campaigns available right now. Check back soon!</p>';
            return;
        }

        container.innerHTML = campaigns.map(c => `
            <div style="border:1px solid #E5E5E5; border-radius:var(--wix-radius); padding:16px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong>${c.title}</strong>
                    <div style="font-size:13px; color:var(--wix-color-text-light); margin-top:4px;">
                        ${c.politician} — ${c.political_office ? c.political_office.replace('_',' ') : 'Official'}
                        ${c.is_live ? '<span class="wix-badge wix-badge-danger">LIVE</span>' : ''}
                    </div>
                    <div style="font-size:12px; color:var(--wix-color-text-light); margin-top:2px;">
                        ${c.governance_level ? c.governance_level.charAt(0).toUpperCase() + c.governance_level.slice(1) : ''} 
                        &middot; ${c.media_duration ? Math.round(c.media_duration/60) + ' min' : 'Live'}
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:20px; font-weight:700; color:var(--wix-color-success);">$${parseFloat(c.payout).toFixed(2)}</div>
                    <button class="wix-btn wix-btn-primary" style="margin-top:8px;" onclick="watchCampaign(${c.id})">
                        ${c.is_live ? 'Join Live' : 'Watch Now'}
                    </button>
                </div>
            </div>
        `).join('');
    }

    function renderHistory(sessions) {
        const container = document.getElementById('view-history');
        if (sessions.length === 0) {
            container.innerHTML = '<p style="color:var(--wix-color-text-light);">No views yet.</p>';
            return;
        }

        container.innerHTML = `<table>
            <thead><tr><th>Campaign</th><th>Watched</th><th>Earned</th><th>Status</th></tr></thead>
            <tbody>${sessions.map(s => `
                <tr>
                    <td>${s.campaign?.title || 'Unknown'}</td>
                    <td>${s.watch_time_seconds || 0}s</td>
                    <td>$${parseFloat(s.voter_payout_amount).toFixed(2)}</td>
                    <td><span class="wix-badge ${s.payment_status === 'paid' ? 'wix-badge-active' : s.payment_status === 'approved' ? 'wix-badge-pending' : 'wix-badge-danger'}">${s.payment_status}</span></td>
                </tr>
            `).join('')}</tbody>
        </table>`;
    }

    async function watchCampaign(campaignId) {
        const res = await fetch(`${API}/voters/${voterId}/campaigns/${campaignId}/watch`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
        });
        const json = await res.json();

        if (json.session_id) {
            // Open the video player
            window.location.href = `{{ route('wix.widget.feed') }}?session=${json.session_id}&url=${encodeURIComponent(json.media_url)}&duration=${json.duration}&payout=${json.payout}`;
        } else {
            alert(json.error || 'Unable to start view');
        }
    }

    function copyReferralCode() {
        const code = document.getElementById('referral-code').value;
        navigator.clipboard.writeText(code);
        alert('Referral code copied!');
    }
</script>
@endpush
@endsection
