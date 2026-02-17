@extends('wix.layouts.dashboard')

@section('title', 'Sign Up – U9itus')

@section('content')
<div style="max-width: 500px; margin: 0 auto;">
    <div class="wix-card" style="text-align: center;">
        <h2>Welcome to U9itus</h2>
        <p style="color: var(--wix-color-text-light); margin-bottom: 24px;">
            Political Loyalty Ads — connecting politicians with voters through paid video messages.
        </p>

        <p style="font-size: 14px; margin-bottom: 16px;">
            To install this app on your Wix site, you'll need a U9itus account.
        </p>

        <a href="{{ url('/register') }}" class="wix-btn wix-btn-primary" style="width: 100%; margin-bottom: 12px;">
            Create Account
        </a>
        <a href="{{ url('/login') }}" class="wix-btn" style="width: 100%; background: #E5E5E5; color: var(--wix-color-text);">
            Already have an account? Log In
        </a>
    </div>
</div>
@endsection
