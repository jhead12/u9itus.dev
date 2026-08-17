@extends(auth()->user()->hasRole('citizen') ? 'standalone.layouts.dashboard' : 'layouts.voter')

@section('title', $group->name.' Members')
@section('page-title', $group->name.' Members')

@section('content')
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="{{ route('groups.public.show', $group) }}" class="text-sm text-slate-400 hover:text-white transition">← Back to {{ $group->name }}</a>
    </div>

    <h1 class="text-2xl font-bold text-white mb-1">Members</h1>
    <p class="text-slate-400 text-sm mb-6">{{ $members->count() }} {{ Str::plural('member', $members->count()) }} in {{ $group->name }}.</p>

    @if(session('status'))
    <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-sm text-emerald-300">
        {{ session('status') }}
    </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl divide-y divide-slate-700/50">
        @foreach($members as $member)
        @php $isRowOwner = $group->isOwner($member); @endphp
        <div class="flex items-center gap-3 px-4 py-3">
            <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}" class="w-9 h-9 rounded-full object-cover flex-shrink-0">

            <div class="min-w-0 flex-1">
                <p class="text-white text-sm font-medium truncate">{{ $member->name }}</p>
                <p class="text-slate-500 text-xs">Joined {{ \Illuminate\Support\Carbon::parse($member->pivot->joined_at)->format('M j, Y') }}</p>
            </div>

            @if($isRowOwner)
                <span class="flex-shrink-0 inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-300">
                    Owner
                </span>
            @else
                @if($member->pivot->role === 'admin')
                <span class="flex-shrink-0 inline-flex items-center rounded-full border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-300">
                    Admin
                </span>
                @endif

                @if($isOwner)
                <form method="POST" action="{{ route('groups.members.role', [$group, $member]) }}" class="flex-shrink-0">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="role" value="{{ $member->pivot->role === 'admin' ? 'member' : 'admin' }}">
                    <button type="submit" class="text-xs text-slate-400 hover:text-white transition">
                        {{ $member->pivot->role === 'admin' ? 'Demote' : 'Promote' }}
                    </button>
                </form>
                @endif

                @if($isAdmin)
                <form method="POST" action="{{ route('groups.members.destroy', [$group, $member]) }}" class="flex-shrink-0"
                      onsubmit="return confirm('Remove {{ addslashes($member->name) }} from the group?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 transition">
                        Remove
                    </button>
                </form>
                @endif
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection
