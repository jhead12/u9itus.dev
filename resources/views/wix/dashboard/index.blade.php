@extends('wix.layouts.dashboard')

@section('title', 'U9itus – Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1>U9itus – Political Loyalty Ads</h1>
        <p class="subtitle">Connect politicians with voters through paid video messages</p>
    </div>
</div>

{{-- Overview Stats --}}
<div class="wix-card">
    <h2>Platform Overview</h2>
    <div class="wix-grid">
        <div class="wix-stat">
            <div class="value">{{ number_format($stats['total_politicians']) }}</div>
            <div class="label">Politicians</div>
        </div>
        <div class="wix-stat">
            <div class="value">{{ number_format($stats['total_voters']) }}</div>
            <div class="label">Registered Voters</div>
        </div>
        <div class="wix-stat">
            <div class="value">{{ number_format($stats['active_campaigns']) }}</div>
            <div class="label">Active Campaigns</div>
        </div>
        <div class="wix-stat">
            <div class="value">{{ number_format($stats['total_views']) }}</div>
            <div class="label">Completed Views</div>
        </div>
    </div>
</div>

{{-- Revenue Card --}}
<div class="wix-card">
    <h2>Revenue &amp; Payouts</h2>
    <div class="wix-grid">
        <div class="wix-stat">
            <div class="value">${{ number_format($stats['total_revenue'], 2) }}</div>
            <div class="label">Platform Revenue</div>
        </div>
        <div class="wix-stat">
            <div class="value">${{ number_format($stats['total_voter_payouts'], 2) }}</div>
            <div class="label">Voter Payouts (Paid)</div>
        </div>
        <div class="wix-stat">
            <div class="value">${{ number_format($stats['pending_payouts'], 2) }}</div>
            <div class="label">Pending Payouts</div>
        </div>
    </div>
</div>

{{-- Per-View Economics --}}
<div class="wix-card">
    <h2>Per-View Economics</h2>
    <p style="color: var(--wix-color-text-light); margin-bottom: 12px;">
        How a single $0.60 view breaks down:
    </p>
    <div class="wix-grid" style="grid-template-columns: repeat(5, 1fr);">
        <div class="wix-stat">
            <div class="value" style="color: var(--wix-color-text);">$0.60</div>
            <div class="label">Politician Pays</div>
        </div>
        <div class="wix-stat">
            <div class="value" style="color: var(--wix-color-success);">$0.25</div>
            <div class="label">Voter Earns</div>
        </div>
        <div class="wix-stat">
            <div class="value" style="color: var(--wix-color-warning);">$0.025</div>
            <div class="label">Referral Commission</div>
        </div>
        <div class="wix-stat">
            <div class="value" style="color: var(--wix-color-text-light);">~$0.07</div>
            <div class="label">Processing + Ops</div>
        </div>
        <div class="wix-stat">
            <div class="value" style="color: var(--wix-color-primary);">~$0.26</div>
            <div class="label">Platform Profit (~43%)</div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="wix-card">
    <h2>Quick Actions</h2>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="{{ route('wix.dashboard.politician') }}" class="wix-btn wix-btn-primary">
            Politician Dashboard
        </a>
        <a href="{{ route('wix.dashboard.voter') }}" class="wix-btn wix-btn-success">
            Voter Dashboard
        </a>
        <a href="{{ route('wix.dashboard.admin') }}" class="wix-btn" style="background: #577083; color: #fff;">
            Admin Panel
        </a>
    </div>
</div>

{{-- Philosophy / Mission --}}
<div class="wix-card">
    <h2>Our Mission</h2>
    <p style="color: var(--wix-color-text-light); font-size: 14px; line-height: 1.8;">
        U9itus bridges the gap between politicians and voters by putting a value on
        attention. In a world increasingly shaped by AI, the human element remains
        irreplaceable — people must still be reached, heard, and engaged.
        Our Loyalty Ads program pays voters to watch political messages in full,
        creating a one-to-one connection between governance and the governed.
        Politicians invest <strong>$0.60 per view</strong>; voters earn
        <strong>$0.25 per completed view</strong> plus referral commissions — everyone wins.
    </p>
</div>
@endsection
