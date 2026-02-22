@extends('standalone.layouts.dashboard')

@section('title', 'User Details')
@section('page-title', 'User Details')

@section('content')
<div class="space-y-6 max-w-3xl">

    <div>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to users</a>
    </div>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- User Card --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-700/50 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-slate-700 flex items-center justify-center text-lg font-bold text-slate-200 shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-base font-semibold text-white">{{ $user->name }}</h2>
                <p class="text-sm text-slate-400">{{ $user->email }}</p>
            </div>
            <span class="ml-auto text-xs px-2.5 py-1 rounded-full {{ $user->user_type === 'politician' ? 'bg-blue-500/10 text-blue-400' : ($user->user_type === 'admin' ? 'bg-red-500/10 text-red-400' : 'bg-emerald-500/10 text-emerald-400') }}">
                {{ $user->user_type ?? '—' }}
            </span>
        </div>

        <dl class="grid grid-cols-2 gap-px bg-slate-700/30">
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Email Verified</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->email_verified_at ? $user->email_verified_at->format('M j, Y') : 'Not verified' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Joined</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->created_at->format('M j, Y') }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">Phone</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->phone ?? '—' }}</dd>
            </div>
            <div class="bg-slate-800/50 px-5 py-4">
                <dt class="text-xs text-slate-500">KYC Status</dt>
                <dd class="mt-1 text-sm text-white">{{ $user->kyc_status ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
        <form method="PUT" action="{{ route('admin.users.suspend', $user) }}">
            @csrf
            @method('PUT')
            <button type="submit"
                class="px-4 py-2 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 text-amber-400 text-sm font-medium transition">
                Suspend User
            </button>
        </form>
    </div>

</div>
@endsection
