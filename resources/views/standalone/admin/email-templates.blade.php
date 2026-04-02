@extends('standalone.layouts.dashboard')

@section('title', 'Email Templates')
@section('page-title', 'Email Notifications')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Header description --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <p class="text-sm text-slate-400 leading-relaxed">
            Manage all transactional email notifications and share messaging used across the platform.
            You can customise the subject / share title, preview text, and optionally replace the
            message body for any entry. Transactional emails can also be disabled individually.
            <span class="text-slate-500">
                Referral / Sharing entries control the default copy that appears in share toolbars
                and referral pages — they are plain-text messages, not HTML email bodies.
            </span>
        </p>
    </div>

    {{-- Template groups --}}
    @forelse($templates as $category => $group)
    @php
        $labelMap = [
            'kyc'      => 'Identity / KYC',
            'campaign' => 'Campaign',
            'billing'  => 'Billing & Credits',
            'payout'   => 'Payouts',
            'account'  => 'Account / Auth',
            'admin'    => 'Admin Alerts',
            'referral' => 'Referral / Sharing',
        ];
        $categoryLabel = $labelMap[$category] ?? ucfirst($category);
    @endphp

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">{{ $categoryLabel }}</h3>
            <span class="text-xs text-slate-500">{{ $group->count() }} {{ Str::plural('template', $group->count()) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Notification</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Subject</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Body</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Last Edited</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($group as $template)
                    <tr class="hover:bg-slate-700/20 transition {{ !$template->is_active ? 'opacity-50' : '' }}">
                        <td class="px-5 py-3">
                            <p class="font-medium text-white">{{ $template->name }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $template->description }}</p>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">
                            @if($template->subject_override)
                                <span class="text-emerald-400">Custom</span>
                                <p class="text-slate-500 truncate max-w-[180px]">{{ $template->subject_override }}</p>
                            @else
                                <span class="text-slate-500 italic">Default</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if($template->hasBodyOverride())
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $template->category === 'referral'
                                        ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                        : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                                    {{ $template->category === 'referral' ? 'Custom Text' : 'Custom HTML' }}
                                </span>
                            @else
                                <span class="text-xs text-slate-500 italic">
                                    {{ $template->category === 'referral' ? 'Default text' : 'Blade template' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <form method="POST" action="{{ route('admin.email-templates.toggle', $template) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-xs px-2.5 py-1 rounded-full border transition
                                    {{ $template->is_active
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/25 hover:bg-emerald-500/20'
                                        : 'bg-slate-700/50 text-slate-400 border-slate-600/50 hover:bg-slate-700' }}">
                                    {{ $template->is_active ? 'Active' : 'Disabled' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">
                            @if($template->lastEditor)
                                {{ $template->lastEditor->name }}<br />
                                <span class="text-slate-500">{{ $template->updated_at->format('M j, Y') }}</span>
                            @else
                                <span class="text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3 justify-end">
                                <a href="{{ route('admin.email-templates.preview', $template) }}"
                                   target="_blank"
                                   class="text-xs text-slate-400 hover:text-slate-300 transition">Preview</a>
                                <a href="{{ route('admin.email-templates.edit', $template) }}"
                                   class="text-xs text-emerald-400 hover:text-emerald-300 transition">Edit →</a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-12 text-center">
        <p class="text-slate-500 text-sm">No email templates found. Run <code class="bg-slate-700 px-1.5 py-0.5 rounded text-xs">php artisan migrate</code> to seed the defaults.</p>
    </div>
    @endforelse

</div>
@endsection
