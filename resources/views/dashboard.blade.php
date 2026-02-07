<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-slate-800/50 backdrop-blur-sm overflow-hidden shadow-xl sm:rounded-2xl border border-slate-700">
                <div class="p-6 text-slate-300">
                    <div class="flex items-center space-x-3 mb-4">
                        <span class="text-3xl">👋</span>
                        <div>
                            <h3 class="text-lg font-semibold text-white">Welcome to Dial4Dough!</h3>
                            <p class="text-slate-400 text-sm">You're successfully logged in</p>
                        </div>
                    </div>
                    <div class="mt-6 p-4 bg-gradient-to-br from-emerald-900/30 to-teal-900/30 rounded-xl border border-emerald-500/30">
                        <p class="text-sm text-emerald-300">
                            🚀 Your account is active and ready to use. Start exploring the platform features!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
