@extends('standalone.layouts.dashboard')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">All Users</h3>
            <span class="text-xs text-slate-500">{{ $users->total() }} total</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">User</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Role</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">KYC</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Joined</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse($users as $u)
                    <tr class="hover:bg-slate-700/20 transition {{ $u->isSuspended() ? 'opacity-60' : '' }}">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-semibold text-slate-300 shrink-0">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-white">{{ $u->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $u->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $u->user_type === 'politician' ? 'bg-blue-500/10 text-blue-400' : ($u->user_type === 'admin' ? 'bg-red-500/10 text-red-400' : 'bg-emerald-500/10 text-emerald-400') }}">
                                {{ $u->user_type ?? '—' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $kyc = $u->kyc_status ?? 'pending';
                                $kycClass = match($kyc) {
                                    'approved' => 'text-emerald-400',
                                    'rejected' => 'text-red-400',
                                    default    => 'text-yellow-400',
                                };
                            @endphp
                            <span class="text-xs {{ $kycClass }}">{{ $kyc }}</span>
                        </td>
                        <td class="px-5 py-3">
                            @if($u->isSuspended())
                                <span class="text-xs text-orange-400">Suspended</span>
                            @elseif($u->email_verified_at)
                                <span class="text-xs text-emerald-400">Active</span>
                            @else
                                <span class="text-xs text-slate-500">Unverified</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">{{ $u->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.users.show', $u) }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition">View →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No users found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $users->links() }}
        </div>
    </div>

</div>
@endsection
