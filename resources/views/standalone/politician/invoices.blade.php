@extends('standalone.layouts.dashboard')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')
<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('politician.billing') }}" class="text-sm text-slate-400 hover:text-white transition">← Billing</a>
    </div>

    @if(!empty($activePaymentMode))
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl px-5 py-3">
        <p class="text-sm text-blue-300">
            Invoices filtered to <span class="font-semibold uppercase">{{ $activePaymentMode }}</span> mode transactions.
        </p>
    </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">All Transactions</h3>
        </div>

        @if($transactions->isEmpty())
            <p class="text-slate-500 text-sm text-center py-16">No transactions on record.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Date</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Reference</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Description</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Credits</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Fee</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Amount</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($transactions as $tx)
                        @php
                            $txColor = match($tx->status) {
                                'succeeded' => 'bg-emerald-500/10 text-emerald-400',
                                'failed'    => 'bg-red-500/10 text-red-400',
                                default     => 'bg-yellow-500/10 text-yellow-400',
                            };
                        @endphp
                        <tr class="hover:bg-slate-700/10 transition">
                            <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                                {{ $tx->created_at?->format('M j, Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-slate-500 text-xs font-mono truncate max-w-[120px]">
                                {{ $tx->uuid ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-slate-300">
                                {{ $tx->description ?? ucfirst(str_replace('_', ' ', $tx->transaction_type)) }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $txColor }}">
                                    {{ ucfirst($tx->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-emerald-400">
                                @if(isset($tx->metadata['credits_amount']))
                                    ${{ number_format($tx->metadata['credits_amount'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-400">
                                @if(isset($tx->metadata['stripe_fee']))
                                    ${{ number_format($tx->metadata['stripe_fee'], 2) }}
                                    @if(isset($tx->metadata['stripe_fee_percent']))
                                        <span class="text-slate-500 text-xs">({{ number_format($tx->metadata['stripe_fee_percent'], 1) }}%)</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-slate-200">
                                ${{ number_format($tx->amount, 2) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if($tx->transaction_type === 'charge' && $tx->status === 'succeeded')
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-blue-500/10 text-blue-300 hover:bg-blue-500/20 transition"
                                            data-invoice-details-btn
                                            data-invoice-id="{{ $tx->id }}"
                                            data-details-url="{{ route('politician.billing.invoices.details', $tx) }}"
                                        >
                                            View Details
                                        </button>
                                        <form method="POST" action="{{ route('politician.billing.invoices.send-receipt', $tx) }}">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition">
                                                Send Receipt
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-500 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
            <div class="px-5 py-4 border-t border-slate-700/50">
                {{ $transactions->links() }}
            </div>
            @endif
        @endif
    </div>

    <dialog id="invoice-details-modal" class="fixed inset-0 z-50 hidden p-0 bg-transparent max-h-none max-w-none w-full h-full" aria-labelledby="invoice-details-title">
        <div id="invoice-details-backdrop" class="absolute inset-0 bg-slate-950/75"></div>

        <div class="absolute inset-y-0 right-0 w-full max-w-2xl bg-slate-900 border-l border-slate-700 shadow-2xl flex flex-col">
            <div class="px-5 py-4 border-b border-slate-700/60 flex items-start justify-between gap-4">
                <div>
                    <h3 id="invoice-details-title" class="text-base font-semibold text-slate-100">Invoice Engagement Details</h3>
                    <p id="invoice-details-subtitle" class="text-xs text-slate-400 mt-1">Estimated attribution by date window.</p>
                </div>
                <button id="invoice-details-close" type="button" class="text-slate-400 hover:text-white transition" aria-label="Close details panel">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div id="invoice-details-loading" class="px-5 py-8 text-sm text-slate-400">Loading engagement details...</div>
            <div id="invoice-details-error" class="px-5 py-8 text-sm text-red-400 hidden"></div>

            <div id="invoice-details-content" class="hidden overflow-y-auto px-5 py-5 space-y-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-slate-800/60 border border-slate-700 rounded-lg p-3">
                        <p class="text-xs text-slate-400">Views Started</p>
                        <p id="metric-views-started" class="text-lg font-semibold text-white mt-1">0</p>
                    </div>
                    <div class="bg-slate-800/60 border border-slate-700 rounded-lg p-3">
                        <p class="text-xs text-slate-400">Views Completed</p>
                        <p id="metric-views-completed" class="text-lg font-semibold text-white mt-1">0</p>
                    </div>
                    <div class="bg-slate-800/60 border border-slate-700 rounded-lg p-3">
                        <p class="text-xs text-slate-400">Avg Watch Time</p>
                        <p id="metric-watch-time" class="text-lg font-semibold text-white mt-1">0s</p>
                    </div>
                    <div class="bg-slate-800/60 border border-slate-700 rounded-lg p-3">
                        <p class="text-xs text-slate-400">Avg Completion</p>
                        <p id="metric-completion" class="text-lg font-semibold text-white mt-1">0%</p>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700 rounded-lg p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Question Engagement</p>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-300">
                        <span>Asked: <strong id="metric-questions-asked" class="text-slate-100">0</strong></span>
                        <span>Replied: <strong id="metric-questions-replied" class="text-slate-100">0</strong></span>
                        <span>Issue Reports: <strong id="metric-issues" class="text-slate-100">0</strong></span>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700 rounded-lg p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Advanced Tracking</p>
                    <div class="mt-2 space-y-1 text-sm">
                        <p id="metric-replay" class="text-slate-300">Replay Presses: Not yet instrumented</p>
                        <p id="metric-heatmap" class="text-slate-500">Heatmap: Not enabled yet</p>
                    </div>
                </div>

                <div class="bg-slate-800/40 border border-slate-700 rounded-lg p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Campaign Breakdown</p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-700/70 text-slate-500">
                                    <th class="text-left py-2 pr-3">Campaign</th>
                                    <th class="text-right py-2 px-2">Started</th>
                                    <th class="text-right py-2 px-2">Completed</th>
                                    <th class="text-right py-2 px-2">Questions</th>
                                    <th class="text-right py-2 pl-2">Replies</th>
                                </tr>
                            </thead>
                            <tbody id="invoice-campaign-breakdown" class="divide-y divide-slate-700/40">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </dialog>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('invoice-details-modal');
    const backdrop = document.getElementById('invoice-details-backdrop');
    const closeButton = document.getElementById('invoice-details-close');
    const loadingBlock = document.getElementById('invoice-details-loading');
    const errorBlock = document.getElementById('invoice-details-error');
    const contentBlock = document.getElementById('invoice-details-content');
    const subtitle = document.getElementById('invoice-details-subtitle');
    const campaignBreakdownBody = document.getElementById('invoice-campaign-breakdown');
    const detailButtons = document.querySelectorAll('[data-invoice-details-btn]');

    const metricViewsStarted = document.getElementById('metric-views-started');
    const metricViewsCompleted = document.getElementById('metric-views-completed');
    const metricWatchTime = document.getElementById('metric-watch-time');
    const metricCompletion = document.getElementById('metric-completion');
    const metricQuestionsAsked = document.getElementById('metric-questions-asked');
    const metricQuestionsReplied = document.getElementById('metric-questions-replied');
    const metricIssues = document.getElementById('metric-issues');
    const metricReplay = document.getElementById('metric-replay');
    const metricHeatmap = document.getElementById('metric-heatmap');

    function openModal() {
        modal.classList.remove('hidden');
        if (typeof modal.showModal === 'function' && !modal.open) {
            modal.showModal();
        }
    }

    function closeModal() {
        if (typeof modal.close === 'function' && modal.open) {
            modal.close();
        }
        modal.classList.add('hidden');
    }

    function showLoading() {
        loadingBlock.classList.remove('hidden');
        errorBlock.classList.add('hidden');
        contentBlock.classList.add('hidden');
        errorBlock.textContent = '';
    }

    function showError(message) {
        loadingBlock.classList.add('hidden');
        contentBlock.classList.add('hidden');
        errorBlock.classList.remove('hidden');
        errorBlock.textContent = message;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatDuration(seconds) {
        const totalSeconds = Math.max(0, Number(seconds) || 0);
        const minutes = Math.floor(totalSeconds / 60);
        const remainingSeconds = Math.round(totalSeconds % 60);

        if (minutes === 0) {
            return `${remainingSeconds}s`;
        }

        return `${minutes}m ${remainingSeconds}s`;
    }

    function formatDate(value) {
        if (!value) {
            return 'Unknown';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return 'Unknown';
        }

        return date.toLocaleString();
    }

    function renderCampaignRows(campaigns) {
        if (!Array.isArray(campaigns) || campaigns.length === 0) {
            campaignBreakdownBody.innerHTML = '<tr><td colspan="5" class="py-3 text-center text-slate-500">No campaign activity in this attribution window.</td></tr>';
            return;
        }

        campaignBreakdownBody.innerHTML = campaigns.map((campaign) => {
            return `
                <tr>
                    <td class="py-2.5 pr-3 text-slate-300">${escapeHtml(campaign.title || 'Untitled campaign')}</td>
                    <td class="py-2.5 px-2 text-right text-slate-200">${Number(campaign.views_started || 0)}</td>
                    <td class="py-2.5 px-2 text-right text-slate-200">${Number(campaign.views_completed || 0)}</td>
                    <td class="py-2.5 px-2 text-right text-slate-200">${Number(campaign.question_interactions_asked || 0)}</td>
                    <td class="py-2.5 pl-2 text-right text-slate-200">${Number(campaign.question_interactions_replied || 0)}</td>
                </tr>
            `;
        }).join('');
    }

    function renderSnapshot(snapshot) {
        const metrics = snapshot.metrics || {};
        const attribution = snapshot.attribution || {};

        metricViewsStarted.textContent = Number(metrics.views_started || 0).toLocaleString();
        metricViewsCompleted.textContent = Number(metrics.views_completed || 0).toLocaleString();
        metricWatchTime.textContent = formatDuration(metrics.avg_watch_time_seconds || 0);
        metricCompletion.textContent = `${Number(metrics.avg_completion_percentage || 0).toFixed(1)}%`;
        metricQuestionsAsked.textContent = Number(metrics.question_interactions_asked || 0).toLocaleString();
        metricQuestionsReplied.textContent = Number(metrics.question_interactions_replied || 0).toLocaleString();
        metricIssues.textContent = Number(metrics.issue_reports || 0).toLocaleString();

        if (metrics.replay_tracking_available) {
            metricReplay.className = 'text-slate-300';
            metricReplay.textContent = `Replay Presses: ${Number(metrics.replay_presses || 0).toLocaleString()}`;
        } else {
            metricReplay.className = 'text-slate-500';
            metricReplay.textContent = 'Replay Presses: Not yet instrumented';
        }

        metricHeatmap.textContent = metrics.heatmap_available
            ? 'Heatmap: Enabled'
            : 'Heatmap: Not enabled yet';

        subtitle.textContent = `Estimated attribution window: ${formatDate(attribution.window_start)} to ${formatDate(attribution.window_end)}`;

        renderCampaignRows(snapshot.campaigns || []);

        loadingBlock.classList.add('hidden');
        errorBlock.classList.add('hidden');
        contentBlock.classList.remove('hidden');
    }

    async function fetchInvoiceDetails(url) {
        showLoading();
        openModal();

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to load details for this invoice.');
            }

            const payload = await response.json();
            renderSnapshot(payload.data || {});
        } catch (error) {
            showError(error.message || 'An unexpected error occurred while loading invoice details.');
        }
    }

    detailButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.getAttribute('data-details-url');
            if (!url) {
                return;
            }
            fetchInvoiceDetails(url);
        });
    });

    backdrop.addEventListener('click', closeModal);
    closeButton.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
});
</script>
@endpush
