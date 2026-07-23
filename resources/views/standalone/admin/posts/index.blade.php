@extends('standalone.layouts.dashboard')

@section('title', 'Blog Posts')
@section('page-title', 'Post Moderation')

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

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.posts.index') }}"
        class="flex flex-col lg:flex-row gap-3 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <div class="flex-1 min-w-0">
            <label for="post-search" class="sr-only">Search posts</label>
            <input
                id="post-search"
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by title, slug, or excerpt..."
                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition"
            >
        </div>
        <div>
            <label for="post-status-filter" class="sr-only">Filter by status</label>
            <select id="post-status-filter" name="status"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Statuses</option>
                @foreach($statuses as $status)
                <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="post-author-type-filter" class="sr-only">Filter by author type</label>
            <select id="post-author-type-filter" name="author_type"
                class="w-full lg:w-auto bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                <option value="">All Authors</option>
                @foreach($authorTypes as $class => $label)
                <option value="{{ $class }}" {{ request('author_type') === $class ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
            class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition shrink-0">
            Apply
        </button>
        @if(request('search') || request('status') || request('author_type'))
        <a href="{{ route('admin.posts.index') }}"
            class="px-3 py-2 rounded-lg bg-slate-700/50 hover:bg-slate-700 text-slate-400 text-sm transition shrink-0 text-center">
            Clear
        </a>
        @endif
    </form>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">All Posts</h3>
            <span class="text-xs text-slate-500">{{ $posts->total() }} total</span>
        </div>

        <form id="bulk-posts-form" method="POST" action="{{ route('admin.posts.bulk-action') }}" class="px-5 py-3 border-b border-slate-700/50 bg-slate-900/30">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex items-center gap-2">
                    <select id="bulk-action-select" name="action"
                        class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-emerald-500/50 transition">
                        <option value="">Bulk Actions</option>
                        <option value="approve">Approve (Publish)</option>
                        <option value="unpublish">Unpublish</option>
                        <option value="archive">Archive</option>
                        <option value="restore">Restore</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button id="bulk-apply-btn" type="submit" disabled
                        class="px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition">
                        Apply
                    </button>
                </div>
                <p id="selected-posts-count" class="text-xs text-slate-500">0 selected</p>
            </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left">
                            <input id="select-all-posts" type="checkbox" aria-label="Select all posts on this page"
                                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Title</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Author</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Published</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @forelse($posts as $post)
                    @php
                        $statusBadge = match ($post->status) {
                            \App\Enums\PostStatus::Published       => 'bg-emerald-500/10 text-emerald-400',
                            \App\Enums\PostStatus::PendingApproval => 'bg-yellow-500/10 text-yellow-400',
                            \App\Enums\PostStatus::Draft           => 'bg-slate-500/10 text-slate-400',
                            \App\Enums\PostStatus::Archived        => 'bg-red-500/10 text-red-400',
                        };
                    @endphp
                    <tr class="hover:bg-slate-700/20 transition {{ $post->status === \App\Enums\PostStatus::Archived ? 'opacity-60' : '' }}">
                        <td class="px-5 py-3 align-top">
                            <input type="checkbox" name="post_ids[]" value="{{ $post->id }}"
                                class="post-row-checkbox rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40">
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-white">{{ $post->title }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">/{{ $post->slug }}</p>
                            @if($post->excerpt)
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2 max-w-md">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-sm text-slate-300">{{ $post->author?->full_name ?? $post->author?->name ?? '—' }}</span>
                            <p class="text-xs text-slate-500">{{ $authorTypes[$post->author_type] ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusBadge }}">
                                {{ $post->status->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">
                            {{ $post->published_at?->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                @if(in_array($post->status, [\App\Enums\PostStatus::Draft, \App\Enums\PostStatus::PendingApproval, \App\Enums\PostStatus::Archived], true))
                                <form method="POST" action="{{ route('admin.posts.approve', $post) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition">Approve</button>
                                </form>
                                @endif
                                @if($post->status === \App\Enums\PostStatus::Published)
                                <form method="POST" action="{{ route('admin.posts.unpublish', $post) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 transition">Unpublish</button>
                                </form>
                                <form method="POST" action="{{ route('admin.posts.archive', $post) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-slate-500/10 text-slate-400 hover:bg-slate-500/20 transition">Archive</button>
                                </form>
                                @endif
                                @if($post->status === \App\Enums\PostStatus::Archived)
                                <form method="POST" action="{{ route('admin.posts.restore', $post) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20 transition">Restore</button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" class="inline" onsubmit="return confirm('Delete this post? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs px-2 py-1 rounded bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No posts found.
                            @if(request('search') || request('status') || request('author_type'))
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
            {{ $posts->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bulkForm = document.getElementById('bulk-posts-form');
    const selectAll = document.getElementById('select-all-posts');
    const actionSelect = document.getElementById('bulk-action-select');
    const applyButton = document.getElementById('bulk-apply-btn');
    const selectedCount = document.getElementById('selected-posts-count');

    if (!bulkForm || !selectAll || !actionSelect || !applyButton || !selectedCount) {
        return;
    }

    const rowCheckboxes = Array.from(document.querySelectorAll('.post-row-checkbox'));

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
            alert('Select at least one post.');
            return;
        }

        if (!actionSelect.value) {
            event.preventDefault();
            alert('Choose a bulk action.');
            return;
        }

        if (actionSelect.value === 'delete') {
            const confirmed = confirm('Permanently delete ' + checkedCount + ' selected post(s)? This cannot be undone.');
            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    updateSelectionState();
});
</script>
@endpush