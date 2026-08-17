@extends('standalone.layouts.dashboard')

@section('title', 'Edit Ballot Measure — ' . $ballotMeasure->title)
@section('page-title', 'Ballot Measures')

@section('content')
<div class="px-6 py-8 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-8">Edit Ballot Measure</h1>

    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
            <p class="text-red-400 text-sm font-medium mb-1">Please fix the following errors:</p>
            @foreach($errors->all() as $error)
                <p class="text-red-300 text-sm">• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.ballot-measures.update', $ballotMeasure) }}" class="space-y-6">
        @include('standalone.admin.ballot-measures._form')
    </form>
</div>
@endsection
