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

    <form method="GET" action="{{ route('admin.users.index') }}"
        class="flex flex-col lg:flex-row gap-3 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <div class="flex-1 min-w-0">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by name, email, phone, ID, city, state, or office..."
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition"
            >
        </div>
        <div>
            <select name="role"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Roles</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="politician" {{ request('role') === 'politician' ? 'selected' : '' }}>Politician</option>
                <option value="voter" {{ request('role') === 'voter' ? 'selected' : '' }}>Voter</option>
            </select>
        </div>
        <div>
            <select name="kyc"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All KYC</option>
                <option value="approved" {{ request('kyc') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="pending" {{ request('kyc') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="rejected" {{ request('kyc') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <select name="account_status"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Account Status</option>
                <option value="active" {{ request('account_status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="unverified" {{ request('account_status') === 'unverified' ? 'selected' : '' }}>Unverified</option>
                <option value="suspended" {{ request('account_status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
        </div>
        <button type="submit"
            class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition shrink-0">
            Apply
        </button>
        @if(request('search') || request('role') || request('kyc') || request('account_status'))
        <a href="{{ route('admin.users.index') }}"
            class="px-3 py-2 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-sm transition shrink-0 text-center">
            Clear
        </a>
        @endif
    </form>

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
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No users found.
                            @if(request('search') || request('role') || request('kyc') || request('account_status'))
                                Try clearing or adjusting your filters.
                            @endif
                        </td>
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
