@extends('standalone.layouts.dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="flex items-center justify-center min-h-64">
    <div class="max-w-md w-full text-center">

        {{-- Warning icon --}}
        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-amber-500/10 border border-amber-500/30 mx-auto mb-5">
            <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>

        <h2 class="text-lg font-semibold text-slate-200 mb-2">Account Setup Incomplete</h2>
        <p class="text-slate-400 text-sm mb-6">
            Hi <span class="text-white font-medium">{{ $user->name }}</span>, your account doesn't have a role assigned yet.
            Please log out and register with the correct account type, or contact support if you believe this is an error.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('register') }}"
               class="inline-flex items-center justify-center px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold rounded-xl transition-colors">
                Register an Account
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full inline-flex items-center justify-center px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-semibold rounded-xl transition-colors">
                    Log Out
                </button>
            </form>
        </div>

        <p class="mt-5 text-xs text-slate-600">
            Account: {{ $user->email }} &nbsp;·&nbsp; user_type: {{ $user->user_type ?? 'none' }}
        </p>
    </div>
</div>
@endsection
