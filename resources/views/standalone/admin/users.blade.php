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

    @if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}"
        class="flex flex-col lg:flex-row gap-3 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <div class="flex-1 min-w-0">
            <label for="user-search" class="sr-only">Search users</label>
            <input
                id="user-search"
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by name, email, phone, ID, city, state, or office..."
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition"
            >
        </div>
        <div>
            <label for="user-role-filter" class="sr-only">Filter by role</label>
            <select id="user-role-filter" name="role"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Roles</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="politician" {{ request('role') === 'politician' ? 'selected' : '' }}>Politician</option>
                <option value="voter" {{ request('role') === 'voter' ? 'selected' : '' }}>Voter</option>
            </select>
        </div>
        <div>
            <label for="user-kyc-filter" class="sr-only">Filter by KYC status</label>
            <select id="user-kyc-filter" name="kyc"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All KYC</option>
                <option value="approved" {{ request('kyc') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="pending" {{ request('kyc') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="rejected" {{ request('kyc') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <label for="user-account-status-filter" class="sr-only">Filter by account status</label>
            <select id="user-account-status-filter" name="account_status"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Account Status</option>
                <option value="active" {{ request('account_status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="unverified" {{ request('account_status') === 'unverified' ? 'selected' : '' }}>Unverified</option>
                <option value="suspended" {{ request('account_status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
        </div>
        <div>
            <label for="user-authentic-verifier-filter" class="sr-only">Filter by authentic user verifier status</label>
            <select id="user-authentic-verifier-filter" name="authentic_user_verifier"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Authentic User Verifier</option>
                <option value="pending" {{ request('authentic_user_verifier') === 'pending' ? 'selected' : '' }}>Pending Migration</option>
                <option value="completed" {{ request('authentic_user_verifier') === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
        </div>
        <button type="submit"
            class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition shrink-0">
            Apply
        </button>
        @if(request('search') || request('role') || request('kyc') || request('account_status') || request('authentic_user_verifier'))
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

        <form id="bulk-users-form" method="POST" action="{{ route('admin.users.bulk-action') }}" class="px-5 py-3 border-b border-slate-700/50 bg-slate-900/30">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex items-center gap-2">
                    <select id="bulk-action-select" name="action"
                        class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                        <option value="">Bulk Actions</option>
                        <option value="suspend">Suspend</option>
                        <option value="unsuspend">Unsuspend</option>
                        <option value="kyc_approve">Approve KYC</option>
                        <option value="kyc_reject">Reject KYC</option>
                    </select>
                    <button id="bulk-apply-btn" type="submit" disabled
                        class="px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition">
                        Apply
                    </button>
                </div>
                <p id="selected-users-count" class="text-xs text-slate-500">0 selected</p>
            </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left">
                            <input id="select-all-users" type="checkbox" aria-label="Select all users on this page"
                                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                        </th>
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
                        <td class="px-5 py-3 align-top">
                            <input type="checkbox" name="user_ids[]" value="{{ $u->id }}"
                                class="user-row-checkbox rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $u->avatar_url }}"
                                     alt="{{ $u->name }}"
                                     class="w-8 h-8 rounded-full object-cover shrink-0">
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
                            @if($u->user_type === 'voter')
                                <span class="text-xs text-blue-400" title="Voter identity is verified via Stripe Connect">Stripe</span>
                            @else
                            @php
                                $kyc = $u->kyc_status ?? 'pending';
                                $kycClass = match($kyc) {
                                    'approved' => 'text-emerald-400',
                                    'rejected' => 'text-red-400',
                                    default    => 'text-yellow-400',
                                };
                            @endphp
                            <span class="text-xs {{ $kycClass }}">{{ $kyc }}</span>
                            @endif
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
                        <td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">
                            No users found.
                            @if(request('search') || request('role') || request('kyc') || request('account_status') || request('authentic_user_verifier'))
                                Try clearing or adjusting your filters.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </form>

        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $users->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bulkForm = document.getElementById('bulk-users-form');
    const selectAll = document.getElementById('select-all-users');
    const actionSelect = document.getElementById('bulk-action-select');
    const applyButton = document.getElementById('bulk-apply-btn');
    const selectedCount = document.getElementById('selected-users-count');

    if (!bulkForm || !selectAll || !actionSelect || !applyButton || !selectedCount) {
        return;
    }

    const rowCheckboxes = Array.from(document.querySelectorAll('.user-row-checkbox'));

    const updateSelectionState = function () {
        const checkedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;
        selectedCount.textContent = checkedCount + ' selected';

        const allChecked = checkedCount > 0 && checkedCount === rowCheckboxes.length;
        selectAll.checked = allChecked;
        selectAll.indeterminate = checkedCount > 0 && !allChecked;

        applyButton.disabled = checkedCount === 0;
    };

    selectAll.addEventListener('change', function () {
        rowCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        updateSelectionState();
    });

    rowCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectionState);
    });

    bulkForm.addEventListener('submit', function (event) {
        const checkedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;

        if (checkedCount === 0) {
            event.preventDefault();
            alert('Select at least one user.');
            return;
        }

        if (!actionSelect.value) {
            event.preventDefault();
            alert('Choose a bulk action.');
            return;
        }

        if (actionSelect.value === 'suspend' || actionSelect.value === 'kyc_reject') {
            const actionLabel = actionSelect.value === 'suspend' ? 'suspend' : 'reject KYC for';
            const confirmed = confirm('Apply this action to ' + checkedCount + ' selected user(s): ' + actionLabel + '?');

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    updateSelectionState();
});
</script>
@endpush
