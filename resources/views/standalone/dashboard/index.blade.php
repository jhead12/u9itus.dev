@extends('standalone.layouts.dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="flex items-center justify-center min-h-64">
    <div class="text-center">
        <h2 class="text-lg font-semibold text-slate-200 mb-2">Welcome, {{ $user->name }}</h2>
        <p class="text-slate-500 text-sm">Your role dashboard is being prepared.</p>
    </div>
</div>
@endsection
