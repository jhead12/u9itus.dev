<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'U9itus') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-900 text-white antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
            <div class="mb-8">
                <a href="/" class="flex items-center space-x-2 text-3xl font-bold hover:opacity-80 transition">
                    <span class="font-bold">DIAL</span><span class="text-emerald-400">4</span><span class="font-bold">DOUGH</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md px-6 py-8 bg-slate-800/50 backdrop-blur-sm shadow-2xl overflow-hidden sm:rounded-2xl border border-slate-700">
                {{ $slot }}
            </div>

            <div class="mt-6">
                <a href="/" class="text-slate-400 hover:text-emerald-400 transition text-sm">
                    ← Back to home
                </a>
            </div>
        </div>
    </body>
</html>
