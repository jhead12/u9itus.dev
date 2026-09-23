<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $organization->name }} — Voter Guide</title>
    @if ($organization->description)
        <meta name="description" content="{{ $organization->description }}">
    @endif
    @vite(['resources/js/portal-builder/app.jsx'])
</head>
<body style="margin:0;">
    <div id="portal-root" data-mode="render"></div>
    <script id="portal-builder-data" type="application/json">
        {{ Illuminate\Support\Js::from([
            'initialData' => $organization->portal_layout ?: ['content' => [], 'root' => []],
            'portalData' => $portalData,
            'canEndorseCandidates' => $organization->canEndorseCandidates(),
        ]) }}
    </script>

    @unless($embed)
        <footer style="text-align:center;padding:1rem;color:#9ca3af;font-size:0.75rem;">
            Powered by <a href="{{ url('/') }}" style="color:#9ca3af;">U9itus</a>
        </footer>
    @endunless
</body>
</html>
