@extends('layouts.voter')

@section('title', 'Link Unavailable')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">

        <div class="w-20 h-20 rounded-full bg-red-900/40 border border-red-500/30 flex items-center justify-center mx-auto mb-6">
            @if(($reason ?? '') === 'already_used')
                <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @elseif(($reason ?? '') === 'expired')
                <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @else
                <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
            @endif
        </div>

        <h1 class="text-2xl font-bold text-white mb-3">
            @if(($reason ?? '') === 'already_used') Link Already Used
            @elseif(($reason ?? '') === 'expired') Link Expired
            @elseif(($reason ?? '') === 'unavailable') Ad Unavailable
            @else Invalid Link
            @endif
        </h1>

        <p class="text-slate-400 mb-8">{{ $message ?? 'This viewing link is no longer valid.' }}</p>

        @auth
            <a href="{{ route('voter.dashboard') }}"
               class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-6 py-3 rounded-xl transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Go to Dashboard
            </a>
            <p class="text-slate-500 text-xs mt-4">New ad invitations are sent to your email when campaigns become available.</p>
        @else
            <p class="text-slate-500 text-sm">New ad invitations are sent via email when campaigns become available.</p>
        @endauth

    </div>
</div>
@endsection
