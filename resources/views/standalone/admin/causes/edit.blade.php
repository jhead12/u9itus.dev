@extends('standalone.layouts.dashboard')

@section('title', 'Edit Cause — ' . $cause->title)
@section('page-title', 'Causes')

@section('content')
<div class="px-6 py-8 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-8">Edit Cause</h1>

    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
            <p class="text-red-400 text-sm font-medium mb-1">Please fix the following errors:</p>
            @foreach($errors->all() as $error)
                <p class="text-red-300 text-sm">• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.causes.update', $cause) }}" class="space-y-6">
        @include('standalone.admin.causes._form')
    </form>
</div>
@endsection
