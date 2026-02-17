@extends('wix.layouts.dashboard')

@section('title', 'Politician Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1>Politician Dashboard</h1>
        <p class="subtitle">Create and manage your political message campaigns</p>
    </div>
    <button class="wix-btn wix-btn-primary" onclick="showCreateCampaign()">+ New Campaign</button>
</div>

{{-- Profile Section --}}
<div class="wix-card" id="profile-section">
    <h2>Your Profile</h2>
    <form id="profile-form" onsubmit="saveProfile(event)">
        <div class="wix-grid" style="grid-template-columns: 1fr 1fr;">
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Full Name</label>
                <input type="text" name="full_name" id="full_name" required style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Political Office</label>
                <select name="political_office" id="political_office" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
                    <option value="">Select office...</option>
                    @foreach(config('u9itus.political_offices', []) as $office)
                        <option value="{{ $office }}">{{ ucwords(str_replace('_', ' ', $office)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Governance Level</label>
                <select name="governance_level" id="governance_level" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
                    <option value="">Select level...</option>
                    @foreach(config('u9itus.governance_levels', []) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">State</label>
                <input type="text" name="state" id="state" maxlength="2" placeholder="e.g. CA" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">District</label>
                <input type="text" name="district" id="district" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Party Affiliation</label>
                <input type="text" name="party_affiliation" id="party_affiliation" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
        </div>
        <div style="margin-bottom: 12px;">
            <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Bio</label>
            <textarea name="bio" id="bio" rows="3" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;"></textarea>
        </div>
        <button type="submit" class="wix-btn wix-btn-primary">Save Profile</button>
    </form>
</div>

{{-- Campaign List --}}
<div class="wix-card" id="campaigns-section">
    <h2>Your Campaigns</h2>
    <div id="campaigns-list">
        <p style="color: var(--wix-color-text-light);">Loading campaigns...</p>
    </div>
</div>

{{-- Create Campaign Modal --}}
<div id="create-campaign-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:1000; display:none; align-items:center; justify-content:center;">
    <div class="wix-card" style="max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
        <h2>Create New Campaign</h2>
        <form id="campaign-form" onsubmit="createCampaign(event)">
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Campaign Title</label>
                <input type="text" name="title" required style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Message Summary</label>
                <textarea name="message_summary" rows="3" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;"></textarea>
            </div>
            <div class="wix-grid" style="grid-template-columns: 1fr 1fr;">
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Type</label>
                    <select name="campaign_type" required style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
                        <option value="video">Video Message</option>
                        <option value="live_feed">Live Feed</option>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Governance Level</label>
                    <select name="governance_level" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
                        @foreach(config('u9itus.governance_levels', []) as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Video URL</label>
                <input type="url" name="media_url" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
            <div class="wix-grid" style="grid-template-columns: 1fr 1fr;">
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Video Duration (seconds)</label>
                    <input type="number" name="media_duration" min="{{ config('u9itus.min_video_duration') }}" max="{{ config('u9itus.max_video_duration') }}" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">Total Views Requested</label>
                    <input type="number" name="total_views_requested" required min="10" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">
                    Total Budget ($) — <strong>$0.60 per view</strong>
                </label>
                <input type="number" name="total_budget" required min="6" step="0.01" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            </div>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="wix-btn" style="background:#E5E5E5;" onclick="hideCreateCampaign()">Cancel</button>
                <button type="submit" class="wix-btn wix-btn-primary">Create Campaign</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const API = window.D4D.apiBase;
    let politicianId = null;

    function showCreateCampaign() {
        document.getElementById('create-campaign-modal').style.display = 'flex';
    }
    function hideCreateCampaign() {
        document.getElementById('create-campaign-modal').style.display = 'none';
    }

    async function saveProfile(e) {
        e.preventDefault();
        const form = new FormData(e.target);
        const data = Object.fromEntries(form);

        const method = politicianId ? 'PUT' : 'POST';
        const url = politicianId ? `${API}/politicians/${politicianId}` : `${API}/politicians`;

        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
            body: JSON.stringify(data),
        });
        const json = await res.json();
        if (json.politician) {
            politicianId = json.politician.id;
            alert('Profile saved!');
            loadCampaigns();
        }
    }

    async function createCampaign(e) {
        e.preventDefault();
        if (!politicianId) { alert('Please save your profile first.'); return; }

        const form = new FormData(e.target);
        const data = Object.fromEntries(form);

        const res = await fetch(`${API}/politicians/${politicianId}/campaigns`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
            body: JSON.stringify(data),
        });
        const json = await res.json();
        if (json.campaign) {
            hideCreateCampaign();
            e.target.reset();
            loadCampaigns();
        }
    }

    async function loadCampaigns() {
        if (!politicianId) return;
        const res = await fetch(`${API}/politicians/${politicianId}/campaigns`);
        const json = await res.json();
        const list = document.getElementById('campaigns-list');

        if (!json.data || json.data.length === 0) {
            list.innerHTML = '<p style="color:var(--wix-color-text-light);">No campaigns yet. Click "+ New Campaign" to get started.</p>';
            return;
        }

        list.innerHTML = `<table>
            <thead><tr><th>Title</th><th>Type</th><th>Views</th><th>Budget</th><th>Status</th></tr></thead>
            <tbody>${json.data.map(c => `
                <tr>
                    <td>${c.title}</td>
                    <td>${c.campaign_type === 'live_feed' ? 'Live Feed' : 'Video'}</td>
                    <td>${c.views_completed} / ${c.total_views_requested}</td>
                    <td>$${parseFloat(c.total_budget).toFixed(2)}</td>
                    <td><span class="wix-badge ${c.status === 'active' ? 'wix-badge-active' : c.approval_status === 'pending' ? 'wix-badge-pending' : 'wix-badge-danger'}">${c.status}</span></td>
                </tr>
            `).join('')}</tbody>
        </table>`;
    }
</script>
@endpush
@endsection
