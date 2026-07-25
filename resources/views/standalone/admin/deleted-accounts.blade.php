@extends('standalone.layouts.dashboard')

@section('title', 'Deleted Accounts')
@section('page-title', 'Deleted Accounts')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.deleted-accounts.index') }}" class="flex gap-3">
        <input type="text" name="q" value="{{ request('q') }}"
            placeholder="Search by email or name…"
            class="flex-1 bg-slate-800/50 border border-slate-700/50 rounded-lg px-4 py-2 text-sm text-slate-300 placeholder-slate-600 focus:outline-none focus:border-slate-500">
        <button type="submit"
            class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm font-medium transition">
            Search
        </button>
        @if(request('q'))
        <a href="{{ route('admin.deleted-accounts.index') }}"
            class="px-4 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium transition hover:bg-slate-700">
            Clear
        </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">Archived Deleted Accounts</h3>
            <span class="text-xs text-slate-500">{{ $records->total() }} total</span>
        </div>

        @if($records->isEmpty())
        <div class="px-5 py-10 text-center text-sm text-slate-500">
            No deleted accounts found.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="text-left px-5 py-3 text-xs text-slate-500 font-medium">Email</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-500 font-medium">Name</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-500 font-medium">Type</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-500 font-medium">Deleted</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-500 font-medium">Reason</th>
                        <th class="text-left px-4 py-3 text-xs text-slate-500 font-medium">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($records as $record)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-3 text-slate-300 font-mono text-xs">{{ $record->email }}</td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ trim($record->first_name . ' ' . $record->last_name) ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $record->user_type === 'politician' ? 'bg-blue-500/10 text-blue-400' :
                                   ($record->user_type === 'admin' ? 'bg-red-500/10 text-red-400' : 'bg-slate-700 text-slate-300') }}">
                                {{ $record->user_type ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap">
                            {{ $record->deleted_at->format('M j, Y') }}
                            <span class="text-slate-500">{{ $record->deleted_at->format('g:i a') }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate">
                            {{ $record->deletion_reason ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($record->isRestored())
                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400">
                                Restored {{ $record->restored_at->format('M j, Y') }}
                            </span>
                            @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-400">Deleted</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if(!$record->isRestored())
                            <button type="button"
                                onclick="const m=document.getElementById('restore-modal-{{ $record->id }}'); m.classList.remove('hidden'); m.focus();"
                                class="text-xs px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 text-emerald-400 transition">
                                Restore
                            </button>
                            @else
                            <a href="{{ route('admin.users.show', $record->restored_user_id) }}"
                                class="text-xs px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 transition">
                                View User
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $records->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</div>

{{-- Restore confirmation modals (one per non-restored record) --}}
@foreach($records as $record)
@if(!$record->isRestored())
<div id="restore-modal-{{ $record->id }}"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
     role="dialog" aria-modal="true" aria-labelledby="restore-modal-{{ $record->id }}-title" tabindex="-1"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-slate-900 border border-emerald-500/30 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4">
        <h3 id="restore-modal-{{ $record->id }}-title" class="text-base font-semibold text-emerald-400">Restore Account</h3>
        <p class="text-sm text-slate-400">
            This will create a <strong class="text-white">new account</strong> for
            <span class="text-white font-medium">{{ $record->email }}</span> with a new user ID.
            A password reset email will be sent so they can log in.
            Historical data (sessions, earnings, campaigns) cannot be recovered.
        </p>
        <form method="POST" action="{{ route('admin.deleted-accounts.restore', $record) }}">
            @csrf
            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition">
                    Confirm Restore
                </button>
                <button type="button"
                    onclick="document.getElementById('restore-modal-{{ $record->id }}').classList.add('hidden')"
                    class="flex-1 px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm font-medium transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach

<script>
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('[role="dialog"]:not(.hidden)').forEach(function (m) {
        m.classList.add('hidden');
    });
});
</script>
@endsection
