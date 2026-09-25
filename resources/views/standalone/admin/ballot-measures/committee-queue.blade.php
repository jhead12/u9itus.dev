@extends('standalone.layouts.dashboard')

@section('title', 'Committee Review Queue')
@section('page-title', 'Ballot Measures')

@section('content')
<div class="px-6 py-8 max-w-5xl mx-auto space-y-6">
    <div>
        <a href="{{ route('admin.ballot-measures.index') }}" class="text-sm text-slate-400 hover:text-white">← Ballot measures</a>
        <h1 class="text-2xl font-bold text-white mt-2">Committee review queue</h1>
        <p class="text-slate-400 text-sm mt-1 max-w-3xl">
            Committee links waiting for a reviewer, riskiest first. The risk priority number (RPN) is severity × occurrence × detectability:
            how soon voters decide the measure, how many links share the same problem, and how hard that problem is to catch automatically.
            The nightly audit also returns verified links here when a new warning appears.
        </p>
    </div>

    @if(session('warning'))
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg px-4 py-3 text-amber-300 text-sm">{{ session('warning') }}</div>
    @endif

    @forelse($pending as $link)
        <div class="space-y-1">
            <a href="{{ route('admin.ballot-measures.committees', $link->ballot_measure_id) }}" class="text-xs text-slate-400 hover:text-white">
                {{ $link->ballotMeasure?->placeLabel() }}{{ $link->ballotMeasure?->measure_number ? ' · #'.$link->ballotMeasure->measure_number : '' }} — {{ $link->ballotMeasure?->title }}
            </a>
            @include('standalone.admin.ballot-measures._committee-row', ['link' => $link])
        </div>
    @empty
        <p class="text-slate-500 text-sm">Nothing waiting for review.</p>
    @endforelse

    {{ $pending->links() }}
</div>
@endsection
