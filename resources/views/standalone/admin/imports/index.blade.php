@extends('standalone.layouts.dashboard')

@section('title', 'Data Imports')
@section('page-title', 'Data Imports')

@section('content')
<div class="min-h-screen bg-gray-950 text-gray-100 py-12 px-6 sm:px-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">State Data Imports</h1>
            <p class="text-gray-400">Monitor scheduled politician profile syncs and import health</p>
        </div>

        <!-- Status Summary -->
        @if ($latestRun)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Latest Status</div>
                    <div class="text-2xl font-bold">
                        @if ($latestRun->status === 'success')
                            <span class="text-green-400">✓ Success</span>
                        @elseif ($latestRun->status === 'failed')
                            <span class="text-red-400">✗ Failed</span>
                        @else
                            <span class="text-yellow-400">⊙ {{ ucfirst($latestRun->status) }}</span>
                        @endif
                    </div>
                </div>

                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Created</div>
                    <div class="text-2xl font-bold text-blue-400">{{ $latestRun->created_count ?? 0 }}</div>
                </div>

                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Updated</div>
                    <div class="text-2xl font-bold text-purple-400">{{ $latestRun->updated_count ?? 0 }}</div>
                </div>

                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-1">Last Run</div>
                    <div class="text-sm font-mono text-gray-400">
                        @if ($latestRun->started_at)
                            {{ $latestRun->started_at->setTimezone('America/Los_Angeles')->format('M d, H:i PT') }}
                        @else
                            Never
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Import Logs Table -->
        <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-800 bg-gray-800">
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Date</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">State</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Status</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Created</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Updated</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Skipped</th>
                            <th class="px-6 py-4 text-center text-gray-300 font-semibold">Campaigns</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Exit Code</th>
                            <th class="px-6 py-4 text-left text-gray-300 font-semibold">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($imports as $import)
                            @php
                                $stateCode = null;
                                if (preg_match('/\[state=([A-Z]{2})\]/', (string) $import->output, $stateMatch) === 1) {
                                    $stateCode = $stateMatch[1];
                                }
                            @endphp
                            <tr class="hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 font-mono text-gray-400 whitespace-nowrap">
                                    {{ $import->started_at?->setTimezone('America/Los_Angeles')->format('M d, Y H:i PT') ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-300 whitespace-nowrap">
                                    {{ $stateCode ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($import->status === 'success')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-900/30 text-green-400 border border-green-800">
                                            ✓ Success
                                        </span>
                                    @elseif ($import->status === 'failed')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/30 text-red-400 border border-red-800">
                                            ✗ Failed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-700 text-gray-300 border border-gray-600">
                                            {{ ucfirst($import->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-blue-400">
                                    {{ $import->created_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-purple-400">
                                    {{ $import->updated_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-yellow-400">
                                    {{ $import->skipped_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-pink-400">
                                    {{ $import->campaigns_created_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-400">
                                    {{ $import->exit_code ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    @if ($import->error_message)
                                        <span class="text-red-400 text-xs" title="{{ $import->error_message }}">
                                            {{ Str::limit($import->error_message, 50) }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 text-xs">No errors</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                    No import runs found. Imports will appear here after the first scheduled run at 02:00 PT.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($imports->hasPages())
                <div class="border-t border-gray-800 px-6 py-4 bg-gray-800/30">
                    {{ $imports->links() }}
                </div>
            @endif
        </div>

        <!-- Info Box -->
        <div class="mt-8 bg-blue-900/20 border border-blue-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="text-blue-400 text-lg">i</div>
                <div>
                    <h3 class="font-semibold text-blue-300 mb-1">About State Rotation Imports</h3>
                    <p class="text-sm text-gray-400">
                        This dashboard displays daily state-rotation politician profile imports. Imports are scheduled daily at <strong>02:00 PT</strong>
                        and sync one U.S. state per day from the congress-legislators API.
                        The health check runs hourly to ensure freshness. For operational details, see the
                        <a href="{{ route('admin.dashboard') }}" class="text-blue-400 hover:text-blue-300 underline">operations documentation</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
