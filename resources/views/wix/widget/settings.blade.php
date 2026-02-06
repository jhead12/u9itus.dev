@extends('wix.layouts.dashboard')

@section('title', 'Widget Settings')

@section('content')
<div class="page-header">
    <div>
        <h1>Widget Settings</h1>
        <p class="subtitle">Configure how the voter feed widget appears on your site</p>
    </div>
</div>

<div class="wix-card">
    <h2>Display Options</h2>
    <div style="margin-bottom: 16px;">
        <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">
            Governance Level Filter
        </label>
        <select id="governance-filter" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            <option value="">All Levels</option>
            @foreach(config('dial4dough.governance_levels', []) as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div style="margin-bottom: 16px;">
        <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">
            State Filter
        </label>
        <input type="text" id="state-filter" maxlength="2" placeholder="e.g. CA (leave blank for all)"
            style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
    </div>

    <div style="margin-bottom: 16px;">
        <label style="display:block; font-size:13px; color:var(--wix-color-text-light); margin-bottom:4px;">
            Theme
        </label>
        <select id="theme" style="width:100%; padding:8px 12px; border:1px solid #C1E4FE; border-radius:var(--wix-radius); font-size:14px;">
            <option value="dark">Dark</option>
            <option value="light">Light</option>
        </select>
    </div>

    <button class="wix-btn wix-btn-primary" onclick="saveSettings()">Save Settings</button>
</div>

<div class="wix-card">
    <h2>Embed Instructions</h2>
    <p style="color: var(--wix-color-text-light); margin-bottom: 12px;">
        This widget is automatically available when you install the Dial4Dough app.
        You can also manually embed it using an HTML iframe:
    </p>
    <code style="display:block; background:#F0F4F7; padding:12px; border-radius:var(--wix-radius); font-size:13px; word-break:break-all; color:#162D3D;">
        &lt;iframe src="{{ url('/wix/widget') }}" width="100%" height="600" frameborder="0"&gt;&lt;/iframe&gt;
    </code>
</div>

@push('scripts')
<script>
    function saveSettings() {
        // In a real Wix app, this would use the Wix SDK to persist settings
        alert('Settings saved!');
    }
</script>
@endpush
@endsection
