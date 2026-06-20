<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'U9itus'))</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Bootstrap CSS (CDN) -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-VkW1Y6b1Yq3p1rYyXg8Z2s+3YQ0QK0e1R6bZ0e1Q6z9e2q1Z4Yb3c6h9K1V7K2qD" crossorigin="anonymous">
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-900 text-white antialiased">
        <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
            @include('layouts.navigation')
            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-slate-800/50 shadow-lg border-b border-slate-700">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @else
                @hasSection('header')
                    <header class="bg-slate-800/50 shadow-lg border-b border-slate-700">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            @yield('header')
                        </div>
                    </header>
                @endif
            @endif

            <!-- Page Content -->
            <main class="container py-4">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>
        <!-- Bootstrap JS Bundle (includes Popper) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-9ND8Kq3l9z1X5Y2gZ3f8b1Y2h9K1V7K2qD3e1Q6z9e2q1Z4Yb3c6h9K1V7K2qD9" crossorigin="anonymous"></script>

        <x-feedback-widget />
    </body>
</html>
