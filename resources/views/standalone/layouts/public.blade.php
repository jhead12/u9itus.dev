<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="@yield('meta_description', 'U9itus — civic engagement, political transparency, and community-driven media.')">
    @if(trim($__env->yieldContent('canonical')) !== '')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    @stack('meta')

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Vite assets --}}
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen antialiased">

    {{-- Top Nav Bar --}}
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="{{ route('blog.index') }}" class="text-sm text-slate-300 hover:text-white transition">Blog</a>
                <a href="{{ route('us.map') }}" class="text-sm text-slate-300 hover:text-white transition">Map</a>
                <a href="{{ route('politicians.directory') }}" class="text-sm text-slate-300 hover:text-white transition">Politicians</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                    <a href="{{ route('register.voter') }}" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create Free Account
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
