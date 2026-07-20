@extends('standalone.layouts.dashboard')

@section('title', 'My Blog Posts')
@section('page-title', 'Blog Posts')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-white">My Blog Posts</h2>
            <p class="text-sm text-slate-400">Create and manage articles to drive traffic to your profile and campaigns.</p>
        </div>
        <a href="{{ route(auth()->user()->hasRole('politician') ? 'politician.posts.create' : 'citizen.posts.create') }}"
           class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Post
        </a>
    </div>

    @if(session('success'))
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">
        {{ session('success') }}
    </div>
    @endif

    @if($posts->isEmpty())
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-8 text-center">
        <p class="text-slate-400">No posts yet. Blog posts help you show up in search engines and on the map.</p>
        <a href="{{ route(auth()->user()->hasRole('politician') ? 'politician.posts.create' : 'citizen.posts.create') }}"
           class="inline-flex items-center gap-2 mt-4 text-amber-400 hover:text-amber-300 text-sm font-medium">
            Write your first post →
        </a>
    </div>
    @else
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-900/50 text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Title</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Published</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @foreach($posts as $post)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-4 py-3">
                        <div class="font-medium text-white">{{ $post->title }}</div>
                        @if($post->is_promoted)
                        <span class="text-xs text-amber-400">Promoted</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            @match($post->status->value)
                                @case('published') bg-emerald-500/10 text-emerald-300 @break
                                @case('pending_approval') bg-amber-500/10 text-amber-300 @break
                                @case('archived') bg-slate-600/20 text-slate-400 @break
                                @default bg-slate-600/20 text-slate-400
                            @endmatch">
                            {{ ucfirst(str_replace('_', ' ', $post->status->value)) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-400">
                        {{ $post->published_at?->format('M j, Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($post->status->value === 'published')
                            <a href="{{ route('blog.show', $post) }}" target="_blank" class="text-slate-400 hover:text-white transition">View</a>
                            @endif
                            <a href="{{ route(auth()->user()->hasRole('politician') ? 'politician.posts.edit' : 'citizen.posts.edit', $post) }}"
                               class="text-amber-400 hover:text-amber-300 transition">Edit</a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
    @endif
</div>
@endsection
