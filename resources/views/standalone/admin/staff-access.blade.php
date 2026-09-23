@extends('standalone.layouts.dashboard')
@section('title', 'Staff access')
@section('page-title', 'Staff access')
@section('content')
<div class="max-w-6xl space-y-6">
    <h2 class="text-2xl font-bold">Roles and staff access</h2>
    <p class="text-slate-400">Choose what each team member can do. Role changes apply to all members. Only Super Admins can manage access.</p>
    @if(session('success'))<p role="status" class="text-emerald-300">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="text-red-300">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <details class="rounded-xl border border-slate-700 p-5" @if($errors->any()) open @endif>
        <summary class="font-semibold cursor-pointer">Create a role</summary>
        <form method="POST" action="{{ route('admin.staff.roles.store') }}" class="mt-4 space-y-4">
            @csrf
            @include('standalone.admin.partials.staff-role-fields', ['role' => null])
        </form>
    </details>
    <section class="space-y-3" aria-label="Existing roles">
        @foreach($roles as $role)
            <details class="rounded-xl border border-slate-700 p-4">
                <summary class="cursor-pointer">{{ substr($role->name, 6) }} <span class="text-slate-400 text-sm">· {{ $role->permissions->count() }} permissions</span></summary>
                @if($role->name === \App\Support\AdminAccess::LEGACY)
                    <p class="mt-3 text-sm text-slate-400">Protected migration role. Replace assignments with specific staff roles to narrow access.</p>
                @else
                    <form method="POST" action="{{ route('admin.staff.roles.update', $role) }}" class="mt-4 space-y-4">
                        @csrf @method('PUT')
                        @include('standalone.admin.partials.staff-role-fields')
                    </form>
                    <form method="POST" action="{{ route('admin.staff.roles.destroy', $role) }}" class="mt-4">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-300">Delete unused role</button>
                    </form>
                @endif
            </details>
        @endforeach
    </section>
    @if($pendingContributorRequests->isNotEmpty())
    <section class="space-y-3" aria-labelledby="contributor-requests-heading">
        <h3 id="contributor-requests-heading" class="text-xl font-semibold">Pending Web Reporter requests</h3>
        <p class="text-slate-400 text-sm">Requested from onboarding or the profile page. Approving grants only source-submission access — nothing else changes on the account.</p>
        @foreach($pendingContributorRequests as $requester)
            <div class="rounded-xl border border-amber-800/60 bg-amber-950/20 p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $requester->name }} <span class="font-normal text-slate-400">{{ $requester->email }}</span></p>
                    <p class="text-xs text-slate-400">Requested {{ $requester->chatter_contributor_requested_at->diffForHumans() }}</p>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.staff.contributor', $requester) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="enabled" value="1">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.staff.contributor-request.dismiss', $requester) }}">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Dismiss</button>
                    </form>
                </div>
            </div>
        @endforeach
    </section>
    @endif
    <section class="space-y-4" aria-labelledby="staff-heading">
        <h3 id="staff-heading" class="text-xl font-semibold">Assign access</h3>
        <form method="GET" class="flex gap-3">
            <label class="flex-1"><span class="sr-only">Find an existing account</span><input name="q" value="{{ request('q') }}" placeholder="Search existing accounts by name or email" class="w-full rounded-lg bg-slate-800 border-slate-600"></label>
            <button class="rounded-lg bg-slate-700 px-4">Search</button>
        </form>
        @foreach($users as $staff)
            <form method="POST" action="{{ route('admin.staff.contributor', $staff) }}" class="rounded-xl border border-emerald-900 p-4 space-y-2">
                @csrf @method('PUT')
                <h4 class="font-semibold">Web Reporter: {{ $staff->name }} · {{ $staff->email }}</h4>
                <input type="hidden" name="enabled" value="0">
                <label class="block text-sm"><input type="checkbox" name="enabled" value="1" @checked($staff->hasRole(\App\Support\ChatterContributorAccess::ROLE))> Can submit public sources and view own submissions</label>
                @if($staff->chatter_contributor_requested_at && ! $staff->hasRole(\App\Support\ChatterContributorAccess::ROLE))
                    <p class="text-xs text-amber-300">Requested {{ $staff->chatter_contributor_requested_at->diffForHumans() }} — check the box and save to approve.</p>
                @endif
                <p class="text-xs text-slate-400">Separate from staff access below. Does not grant admin access, change existing roles, or remove other access. Web Reporter page: <a class="text-emerald-300 underline" href="{{ route('contributor.chatter.index') }}">Submit a source</a>.</p>
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm">Save contributor access</button>
            </form>
            <form method="POST" action="{{ route('admin.staff.assign', $staff) }}" class="rounded-xl border border-slate-700 p-5 space-y-3">
                @csrf @method('PUT')
                <h4 class="font-semibold">{{ $staff->name }} <span class="font-normal text-slate-400">{{ $staff->email }}</span></h4>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($roles as $role)
                        <label class="text-sm"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($staff->roles->contains('id', $role->id))> {{ substr($role->name, 6) }}</label>
                    @endforeach
                </div>
                <label class="block text-amber-300 text-sm"><input type="checkbox" name="super_admin" value="1" @checked($staff->hasRole('super_admin'))> Super Admin — full access, including role management</label>
                <details class="text-sm text-slate-400"><summary class="cursor-pointer">Current effective access</summary>
                    <p class="mt-2">{{ \App\Support\AdminAccess::owner($staff) ? 'All capabilities and staff administration' : (collect($catalog)->filter(fn ($label, $key) => \App\Support\AdminAccess::allowed($staff, $key))->values()->join(', ') ?: 'No operational access') }}</p>
                </details>
                <p class="text-xs text-slate-400">Clear all roles and Super Admin to revoke operational access. This also clears direct permission overrides.</p>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold">Save access</button>
            </form>
        @endforeach
        {{ $users->links() }}
    </section>
    <section class="space-y-3" aria-label="Access audit history">
        <h3 class="text-xl font-semibold">Recent access changes</h3>
        @forelse($audits as $audit)
            <details class="rounded-lg bg-slate-800 p-3 text-sm">
                <summary class="cursor-pointer">{{ $audit->created_at }} · {{ $audit->action }} · {{ $audit->target }} · {{ $audit->actor_id ? 'Account '.$audit->actor_id : 'CLI recovery' }}</summary>
                <p class="mt-2 break-words text-slate-400">Before: {{ $audit->before }}</p>
                <p class="mt-2 break-words text-slate-300">After: {{ $audit->after }}</p>
            </details>
        @empty
            <p class="text-slate-400">No access changes recorded.</p>
        @endforelse
    </section>
</div>
@endsection
