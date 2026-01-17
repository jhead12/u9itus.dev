@extends('layouts.app')

@section('title', 'Viewer Dashboard')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h1>Viewer Dashboard</h1>
        <hr>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h6 class="card-title">Total Earned</h6>
                <h2>${{ number_format($stats['total_earned'], 2) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h6 class="card-title">Pending Earnings</h6>
                <h2>${{ number_format($stats['pending_earnings'], 2) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h6 class="card-title">Total Views</h6>
                <h2>{{ $stats['total_views'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h6 class="card-title">Trust Score</h6>
                <h2>{{ number_format($stats['trust_score'], 1) }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        @if($currentAssignment)
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-play-circle"></i> Current Assignment</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4>{{ $currentAssignment->campaign->title }}</h4>
                            <p class="text-muted mb-2">{{ $currentAssignment->campaign->description }}</p>
                            <p class="mb-1">
                                <strong>Payment:</strong> ${{ number_format($currentAssignment->campaign->payment_per_view, 2) }}<br>
                                <strong>Duration:</strong> {{ $currentAssignment->campaign->media_duration }} seconds<br>
                                <strong>Min Watch Time:</strong> {{ $currentAssignment->campaign->min_watch_time_percent }}%<br>
                                <strong>Expires:</strong> {{ $currentAssignment->expires_at->diffForHumans() }}
                            </p>
                            @if($currentAssignment->status === 'assigned')
                                <span class="badge bg-info">Ready to Watch</span>
                            @elseif($currentAssignment->status === 'in_progress')
                                <span class="badge bg-warning">In Progress</span>
                            @endif
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="{{ route('viewer.watch', $currentAssignment) }}" class="btn btn-lg btn-primary">
                                <i class="bi bi-play-fill"></i> Watch Ad
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">No Active Assignment</h4>
                    <p class="text-muted">You currently don't have any ads assigned. Check back later!</p>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Views</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Watch Time</th>
                                <th>Completion</th>
                                <th>Earned</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAssignments as $assignment)
                                <tr>
                                    <td>
                                        <strong>{{ $assignment->campaign->title }}</strong>
                                    </td>
                                    <td>{{ $assignment->watch_time ?? 0 }}s</td>
                                    <td>
                                        @if($assignment->completion_percentage)
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: {{ $assignment->completion_percentage }}%"
                                                     aria-valuenow="{{ $assignment->completion_percentage }}" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    {{ number_format($assignment->completion_percentage, 0) }}%
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="text-success">${{ number_format($assignment->payment_amount ?? 0, 2) }}</strong>
                                    </td>
                                    <td>
                                        @if($assignment->status === 'completed')
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Completed
                                            </span>
                                        @elseif($assignment->status === 'in_progress')
                                            <span class="badge bg-warning">
                                                <i class="bi bi-hourglass-split"></i> In Progress
                                            </span>
                                        @elseif($assignment->status === 'assigned')
                                            <span class="badge bg-info">
                                                <i class="bi bi-clock"></i> Assigned
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($assignment->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $assignment->created_at->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No viewing history yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
