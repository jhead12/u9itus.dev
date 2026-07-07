<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account — {{ config('app.name', 'U9itus') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 py-8 antialiased">

<div class="w-full max-w-md">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="/" class="inline-block"><img src="{{ asset('media/u9itus-logo.svg') }}" alt="U9itus" class="h-10 mx-auto mb-2"></a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    <div class="bg-slate-800/60 border border-slate-700/50 rounded-2xl p-8 shadow-2xl">
        <h2 class="text-xl font-semibold text-white mb-1">Create your account</h2>
        <p class="text-sm text-slate-400 mb-6">Join the platform as a politician or voter</p>

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            {{-- Account type --}}
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">I am a...</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="account-type-label cursor-pointer">
                        <input type="radio" name="user_type" value="politician" class="sr-only account-type-radio"
                               {{ old('user_type', 'politician') === 'politician' ? 'checked' : '' }}>
                        <div class="account-type-card border border-slate-700 rounded-lg px-4 py-3 text-center text-sm font-medium text-slate-300 hover:border-emerald-500/50 transition">
                            🏛️ Politician
                        </div>
                    </label>
                    <label class="account-type-label cursor-pointer">
                        <input type="radio" name="user_type" value="voter" class="sr-only account-type-radio"
                               {{ old('user_type') === 'voter' ? 'checked' : '' }}>
                        <div class="account-type-card border border-slate-700 rounded-lg px-4 py-3 text-center text-sm font-medium text-slate-300 hover:border-emerald-500/50 transition">
                            🗳️ Voter
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-slate-300 mb-1.5">First name</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="John" />
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-slate-300 mb-1.5">Last name</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                        placeholder="Smith" />
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="you@example.com" />
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-slate-300 mb-1.5">Phone <span class="text-slate-500">(optional)</span></label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="+1 (555) 000-0000" />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                <x-password-input
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="Min. 8 characters"
                />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1.5">Confirm password</label>
                <x-password-input
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition"
                    placeholder="••••••••"
                />
            </div>

            <button type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/50 mt-2">
                Create Account
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition">Sign in</a>
        </p>
    </div>

</div>

<script>
    // Highlight selected account type card
    document.querySelectorAll('.account-type-radio').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.account-type-card').forEach(card => {
                card.classList.remove('border-emerald-500', 'text-emerald-400', 'bg-emerald-500/5');
                card.classList.add('border-slate-700', 'text-slate-300');
            });
            const selected = radio.nextElementSibling;
            selected.classList.remove('border-slate-700', 'text-slate-300');
            selected.classList.add('border-emerald-500', 'text-emerald-400', 'bg-emerald-500/5');
        });
        if (radio.checked) radio.dispatchEvent(new Event('change'));
    });
</script>
</body>
</html>
