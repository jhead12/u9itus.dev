@extends('layouts.app')

@section('title', 'Admin - Assignment Dashboard')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h1>Assignment Dashboard</h1>
        <hr>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Available Viewers</h5>
                <h2 class="text-primary">{{ $availableViewers->count() }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Campaigns Needing Viewers</h5>
                <h2 class="text-primary">{{ $campaignsNeedingViewers->count() }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Auto-Assign Ads</h5>
                <p class="card-text">Automatically assign ads to available viewers based on matching criteria.</p>
                <form method="POST" action="{{ route('admin.assignments.auto-assign') }}" class="d-inline">
                    @csrf
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label for="limit" class="form-label">Number of assignments</label>
                            <input type="number" class="form-control" name="limit" id="limit" value="10" min="1" max="100">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-lightning-fill"></i> Auto-Assign
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Available Viewers</h5>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                @forelse($availableViewers as $viewer)
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <h6 class="mb-1">{{ $viewer->name }}</h6>
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> {{ $viewer->email }}<br>
                                <i class="bi bi-trophy"></i> Trust Score: {{ $viewer->viewer->trust_score ?? 'N/A' }}<br>
                                <i class="bi bi-eye"></i> Total Views: {{ $viewer->viewer->total_views ?? 0 }}
                            </small>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">No available viewers at this time.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Campaigns Needing Viewers</h5>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                @forelse($campaignsNeedingViewers as $campaign)
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <h6 class="mb-1">{{ $campaign->title }}</h6>
                            <small class="text-muted">
                                Advertiser: {{ $campaign->advertiser->user->name ?? 'N/A' }}<br>
                                Views: {{ $campaign->views_completed }}/{{ $campaign->total_views_requested }}<br>
                                Payment: ${{ number_format($campaign->payment_per_view, 2) }} per view
                            </small>
                            <div class="progress mt-2" style="height: 5px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: {{ ($campaign->views_completed / $campaign->total_views_requested) * 100 }}%">
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">No campaigns needing viewers.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Manual Assignment</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.assignments.assign') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-5">
                            <label for="viewer_id" class="form-label">Select Viewer</label>
                            <select class="form-select" name="viewer_id" id="viewer_id" required>
                                <option value="">Choose a viewer...</option>
                                @foreach($availableViewers as $viewer)
                                    <option value="{{ $viewer->id }}">
                                        {{ $viewer->name }} (Trust: {{ $viewer->viewer->trust_score ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="campaign_id" class="form-label">Select Campaign</label>
                            <select class="form-select" name="campaign_id" id="campaign_id" required>
                                <option value="">Choose a campaign...</option>
                                @foreach($campaignsNeedingViewers as $campaign)
                                    <option value="{{ $campaign->id }}">
                                        {{ $campaign->title }} (${{ number_format($campaign->payment_per_view, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-circle"></i> Assign
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
