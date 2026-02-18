@extends('layouts.app')

@section('title', 'Link Unavailable')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4">
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
               class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-6 py-3 rounded-lg transition">
                Go to Dashboard
            </a>
        @else
            <p class="text-slate-500 text-sm">New ad invitations are sent via email when campaigns become available.</p>
        @endauth

    </div>
</div>
@endsection
