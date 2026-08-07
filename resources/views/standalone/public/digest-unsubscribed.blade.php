<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed — {{ config('app.name', 'U9itus') }}</title>
    <meta name="robots" content="noindex">

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-slate-950 min-h-screen antialiased text-slate-100 flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-slate-900/80 border border-slate-700/50 rounded-2xl p-8 text-center">
        <div class="text-4xl mb-4">👋</div>
        <h1 class="text-xl font-semibold text-white mb-2">You're unsubscribed</h1>
        <p class="text-sm text-slate-400 leading-relaxed mb-6">
            You won't get any more saved-places updates. Your favorited districts and
            cities are still saved if you'd like to come back.
        </p>
        <a href="{{ route('us.map') }}"
           class="inline-block bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-6 py-3 rounded-lg transition">
            Back to the Map →
        </a>
    </div>
</body>
</html>
