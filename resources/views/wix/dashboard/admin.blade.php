@extends('wix.layouts.dashboard')

@section('title', 'Admin Panel')

@section('content')
<div class="page-header">
    <div>
        <h1>Admin Panel</h1>
        <p class="subtitle">Approve campaigns, process payouts, manage fraud</p>
    </div>
    <button class="wix-btn wix-btn-success" onclick="processBatchPayouts()">Process Batch Payouts</button>
</div>

{{-- Pending Campaign Approvals --}}
<div class="wix-card">
    <h2>Pending Campaign Approvals</h2>
    @if($pendingCampaigns->isEmpty())
        <p style="color:var(--wix-color-text-light);">No campaigns pending approval.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Politician</th>
                    <th>Type</th>
                    <th>Budget</th>
                    <th>Views</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingCampaigns as $campaign)
                <tr id="campaign-row-{{ $campaign->id }}">
                    <td>{{ $campaign->title }}</td>
                    <td>{{ $campaign->politician->full_name ?? 'N/A' }}</td>
                    <td>{{ $campaign->campaign_type === 'live_feed' ? 'Live Feed' : 'Video' }}</td>
                    <td>${{ number_format($campaign->total_budget, 2) }}</td>
                    <td>{{ number_format($campaign->total_views_requested) }}</td>
                    <td>
                        <button class="wix-btn wix-btn-success" style="font-size:12px; padding:4px 12px;" onclick="approveCampaign({{ $campaign->id }})">Approve</button>
                        <button class="wix-btn wix-btn-danger" style="font-size:12px; padding:4px 12px;" onclick="rejectCampaign({{ $campaign->id }})">Reject</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- All Politicians --}}
<div class="wix-card">
    <h2>Politicians</h2>
    @if($politicians->isEmpty())
        <p style="color:var(--wix-color-text-light);">No politicians registered yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Office</th>
                    <th>Level</th>
                    <th>State</th>
                    <th>Campaigns</th>
                    <th>Total Spent</th>
                    <th>Verified</th>
                </tr>
            </thead>
            <tbody>
                @foreach($politicians as $politician)
                <tr>
                    <td>{{ $politician->full_name }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $politician->political_office ?? 'N/A')) }}</td>
                    <td>{{ config("u9itus.governance_levels.{$politician->governance_level}", $politician->governance_level ?? 'N/A') }}</td>
                    <td>{{ $politician->state ?? 'N/A' }}</td>
                    <td>{{ $politician->campaigns->count() }}</td>
                    <td>${{ number_format($politician->total_spent, 2) }}</td>
                    <td>
                        @if($politician->verified_official)
                            <span class="wix-badge wix-badge-active">Verified</span>
                        @else
                            <span class="wix-badge wix-badge-pending">Pending</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $politicians->links() }}
    @endif
</div>

{{-- Flagged Voters --}}
<div class="wix-card">
    <h2>Flagged Voters (Fraud Review)</h2>
    @if($flaggedVoters->isEmpty())
        <p style="color:var(--wix-color-text-light);">No flagged voters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Trust Score</th>
                    <th>Views</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($flaggedVoters as $voter)
                <tr id="voter-row-{{ $voter->id }}">
                    <td>{{ $voter->full_name }}</td>
                    <td>{{ $voter->email }}</td>
                    <td>{{ number_format($voter->trust_score, 0) }}/100</td>
                    <td>{{ $voter->total_views }}</td>
                    <td>
                        <button class="wix-btn wix-btn-success" style="font-size:12px; padding:4px 12px;" onclick="clearFraudFlag({{ $voter->id }})">Clear Flag</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@push('scripts')
<script>
    const API = window.D4D.apiBase;

    async function approveCampaign(id) {
        if (!confirm('Approve this campaign?')) return;
        const res = await fetch(`${API}/admin/campaigns/${id}/approve`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
        });
        if (res.ok) {
            document.getElementById(`campaign-row-${id}`).remove();
        }
    }

    async function rejectCampaign(id) {
        if (!confirm('Reject this campaign?')) return;
        const res = await fetch(`${API}/admin/campaigns/${id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
        });
        if (res.ok) {
            document.getElementById(`campaign-row-${id}`).remove();
        }
    }

    async function clearFraudFlag(id) {
        if (!confirm('Clear fraud flag for this voter?')) return;
        const res = await fetch(`${API}/admin/voters/${id}/clear-flag`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
        });
        if (res.ok) {
            document.getElementById(`voter-row-${id}`).remove();
        }
    }

    async function processBatchPayouts() {
        if (!confirm('Process batch payouts for all approved views?')) return;
        const res = await fetch(`${API}/admin/payouts/process`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.D4D.csrfToken },
        });
        const json = await res.json();
        alert(json.message || 'Done');
    }
</script>
@endpush
@endsection
