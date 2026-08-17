@extends(auth()->user()->hasRole('citizen') ? 'standalone.layouts.dashboard' : 'layouts.voter')

@section('title', 'Edit Event — '.$group->name)
@section('page-title', 'Edit Event')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('groups.events.index', $group) }}" class="text-sm text-slate-400 hover:text-white transition">← Back to {{ $group->name }}'s events</a>
    </div>

    @include('standalone.events.form', ['prefix' => 'groups', 'event' => $event, 'topics' => $topics, 'routeParams' => ['group' => $group]])
</div>
@endsection
