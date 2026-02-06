<x-app-layout title="{{ $campaign->title }} - Campaign Analytics">

    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">{{ $campaign->title }}</h1>
            <a href="{{ route('advertiser.campaigns.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Campaigns
            </a>
        </div>
    </x-slot>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>{{ $campaign->title }}</h1>
            <a href="{{ route('advertiser.campaigns.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Campaigns
            </a>
        </div>
        <hr>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5>Campaign Details</h5>
                        <p class="text-muted">{{ $campaign->description }}</p>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p><strong>Campaign Type:</strong> {{ ucfirst($campaign->campaign_type) }}</p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-{{ $campaign->status === 'active' ? 'success' : ($campaign->status === 'pending' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($campaign->status) }}
                                    </span>
                                </p>
                                <p><strong>Approval Status:</strong> 
                                    <span class="badge bg-{{ $campaign->approval_status === 'approved' ? 'success' : ($campaign->approval_status === 'pending' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($campaign->approval_status) }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Payment Per View:</strong> ${{ number_format($campaign->payment_per_view, 2) }}</p>
                                <p><strong>Total Budget:</strong> ${{ number_format($campaign->total_budget, 2) }}</p>
                                <p><strong>Min Watch Time:</strong> {{ $campaign->min_watch_time_percent }}%</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        @if($campaign->campaign_type === 'video' && $campaign->media_file_url)
                            <video class="w-100" controls>
                                <source src="{{ Storage::url($campaign->media_file_url) }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        @elseif($campaign->campaign_type === 'audio' && $campaign->media_file_url)
                            <audio class="w-100" controls>
                                <source src="{{ Storage::url($campaign->media_file_url) }}" type="audio/mpeg">
                                Your browser does not support the audio tag.
                            </audio>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Completion Rate</h6>
                <h2 class="text-primary">{{ number_format($analytics['completion_rate'], 1) }}%</h2>
                <div class="progress">
                    <div class="progress-bar" style="width: {{ $analytics['completion_rate'] }}%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Views Completed</h6>
                <h2 class="text-success">{{ $campaign->views_completed }}</h2>
                <small class="text-muted">of {{ $campaign->total_views_requested }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Remaining Views</h6>
                <h2 class="text-warning">{{ $analytics['remaining_views'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Spent</h6>
                <h2 class="text-info">${{ number_format($analytics['total_spent'], 2) }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Campaign Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">Average Watch Time:</label>
                            <p>{{ number_format($analytics['avg_watch_time'], 1) }} seconds</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">Created:</label>
                            <p>{{ $campaign->created_at->format('F d, Y \a\t g:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">View History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Viewer</th>
                                <th>Watch Time</th>
                                <th>Completion %</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Completed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($campaign->assignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->viewer->name ?? 'N/A' }}</td>
                                    <td>{{ $assignment->watch_time }}s</td>
                                    <td>{{ number_format($assignment->completion_percentage, 1) }}%</td>
                                    <td>${{ number_format($assignment->payment_amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $assignment->payment_status === 'approved' ? 'success' : 'warning' }}">
                                            {{ ucfirst($assignment->payment_status) }}
                                        </span>
                                    </td>
                                    <td>{{ $assignment->completed_at ? $assignment->completed_at->format('M d, Y g:i A') : 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No views yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
