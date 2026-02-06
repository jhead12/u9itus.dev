<x-app-layout title="Admin - Assignment Dashboard">

    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
            <div>
                <h1 class="h3 mb-2 mb-md-0">Assignment Dashboard</h1>
                <p class="text-muted mb-0 small">Manage viewer assignments and monitor campaign progress.</p>
            </div>

            <nav class="mt-3 mt-md-0">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/admin') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('admin.assignments.index') }}">Assignments</a>
                    </li>
                </ul>
            </nav>
        </div>
    </x-slot>

    <div class="row row-cols-1 row-cols-md-2 g-2 mb-3">
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                        <i class="bi bi-people-fill fs-5 text-primary"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Available Viewers</h6>
                        <div class="d-flex align-items-baseline gap-2">
                            <h3 class="mb-0 text-primary">{{ $availableViewers->count() }}</h3>
                            <small class="text-muted">ready to assign</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                        <i class="bi bi-bar-chart-line-fill fs-5 text-primary"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Campaigns Needing Viewers</h6>
                        <div class="d-flex align-items-baseline gap-2">
                            <h3 class="mb-0 text-primary">{{ $campaignsNeedingViewers->count() }}</h3>
                            <small class="text-muted">open campaigns</small>
                        </div>
                    </div>
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
                        <div class="d-flex align-items-center mb-2 p-2 rounded border bg-white">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-person-circle fs-3 text-secondary"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $viewer->name }}</div>
                                <div class="small text-muted">
                                    <i class="bi bi-envelope"></i> {{ $viewer->email }} ·
                                    <i class="bi bi-trophy"></i> Trust: {{ $viewer->viewer->trust_score ?? 'N/A' }} ·
                                    <i class="bi bi-eye"></i> Views: {{ $viewer->viewer->total_views ?? 0 }}
                                </div>
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
                        @php
                            $percent = $campaign->total_views_requested ? round(($campaign->views_completed / $campaign->total_views_requested) * 100) : 0;
                        @endphp
                        <div class="d-flex align-items-center mb-2 p-2 rounded border bg-white">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $campaign->title }}</div>
                                <div class="small text-muted">
                                    Advertiser: {{ $campaign->advertiser->user->name ?? 'N/A' }} · 
                                    Views: {{ $campaign->views_completed }}/{{ $campaign->total_views_requested }} · 
                                    Payment: ${{ number_format($campaign->payment_per_view, 2) }} per view
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                            <div class="ms-3 text-end" style="min-width:64px">
                                <div class="fw-bold">{{ $percent }}%</div>
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

</x-app-layout>
