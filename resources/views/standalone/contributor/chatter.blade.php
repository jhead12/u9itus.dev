@extends('standalone.layouts.public')
@section('title', 'Web Reporter source submissions')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-6 text-slate-200">
    <header>
        <p class="text-sm text-amber-300">Web Reporters · Human review required</p>
        <h1 class="mt-2 text-3xl font-bold text-white">Submit a public source</h1>
        <p class="mt-3 text-slate-400">Found news or a public social post relevant to a politician? Share the original link and explain its relevance. Submitting does not publish or verify a claim.</p>
        <a href="{{ route('contributor.chatter.extension') }}" class="mt-3 inline-block text-sm font-semibold text-emerald-300 hover:underline">Get the browser source clipper ↗</a>
    </header>
    @if(session('success'))<p role="status" class="rounded-lg bg-emerald-900/30 p-4 text-emerald-200">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-900/30 p-4 text-red-200"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p data-clip-notice hidden role="status" class="rounded-lg border border-emerald-700 p-4 text-sm text-emerald-200"></p>
    <form data-chatter-clip-form data-restore-clip="{{ session()->hasOldInput() ? 'false' : 'true' }}" method="POST" action="{{ route('contributor.chatter.store') }}" class="rounded-xl border border-slate-700 bg-slate-900/50 p-5 space-y-5">
        @csrf
        @php
            $help = fn (string $label, string $text) => '<button type="button" class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-500 align-middle text-[10px] font-bold text-slate-400 hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-400" aria-label="'.e($label).'" title="'.e($text).'">?</button>';
        @endphp
        @include('standalone.partials.chatter-candidate-picker')
        <label class="block">Platform *{!! $help('Platform help', 'Where you found this — used to show the right platform icon in the review queue.') !!}<select name="platform" required class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900">@foreach(\App\Models\PoliticianChatterItem::PLATFORMS as $value => $label)<option value="{{ $value }}" @selected(old('platform') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block">Original public source URL *{!! $help('Source URL help', 'Must be a public page anyone can open — no login walls, DMs, or private groups.') !!}<input name="source_url" type="url" required maxlength="1000" value="{{ old('source_url') }}" placeholder="https://…" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900"></label>
        <label class="block">Suggested neutral headline *{!! $help('Headline help', 'A short, neutral description for editors — not the final public text.') !!}<input name="headline" required maxlength="240" value="{{ old('headline') }}" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900"></label>
        <label class="block">Why is this relevant? *{!! $help('Relevance help', 'Private note for editors only — never shown publicly, and not the same as the public headline or excerpt.') !!}<textarea name="contributor_notes" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900">{{ old('contributor_notes') }}</textarea><span class="text-xs text-slate-400">Private context for editors, not public profile text.</span></label>
        <label class="block">Short source excerpt (optional){!! $help('Excerpt help', 'Only text you intentionally selected on the public source — never a full article, private message, or sensitive personal information.') !!}<textarea name="source_excerpt" maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-slate-600 bg-slate-900">{{ old('source_excerpt') }}</textarea><span class="text-xs text-slate-400">Only text you intentionally select from the public source. Do not paste full articles, private messages, or sensitive personal information.</span></label>
        <label class="flex gap-3 items-start text-sm"><input type="checkbox" name="public_source" value="1" required @checked(old('public_source')) class="mt-1"><span>I confirm this is a public source and have not included private messages, login credentials, or confidential information.{!! $help('Public source confirmation help', "You're confirming there's nothing private, password-protected, or confidential in what you submitted — editors cannot see it until you confirm this.") !!}</span></label>
        <button class="w-full sm:w-auto rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Submit for review</button>
    </form>
    <section class="space-y-3" aria-labelledby="submission-history">
        <h2 id="submission-history" class="text-xl font-semibold">Your submissions</h2>
        @forelse($items as $item)
            <article class="rounded-xl border border-slate-700 p-4">
                <p class="text-xs text-amber-300">{{ ucfirst($item->moderation_status) }} · {{ $item->created_at->format('M j, Y') }}</p>
                <h3 class="mt-2 font-semibold">{{ $item->headline }}</h3>
                <p class="text-sm text-slate-400">{{ $item->politician->full_name }}</p>
                <a class="mt-2 inline-block text-sm text-emerald-300 break-all" href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">View original source ↗</a>
            </article>
        @empty
            <p class="text-slate-400">No submissions yet. Your sources and review status will appear here.</p>
        @endforelse
        {{ $items->links() }}
    </section>
</div>
@endsection
@push('scripts')
    <script type="module" src="{{ asset('js/chatter-clip-import.js') }}"></script>
@endpush
