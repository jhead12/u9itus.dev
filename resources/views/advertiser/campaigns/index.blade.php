<x-app-layout title="My Campaigns">

    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">My Campaigns</h1>
            <a href="{{ route('advertiser.campaigns.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Campaign
            </a>
        </div>
    </x-slot>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>My Campaigns</h1>
            <a href="{{ route('advertiser.campaigns.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create Campaign
            </a>
        </div>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Payment/View</th>
                                <th>Budget</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($campaigns as $campaign)
                                <tr>
                                    <td>
                                        <strong>{{ $campaign->title }}</strong><br>
                                        <small class="text-muted">{{ Str::limit($campaign->description, 60) }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ ucfirst($campaign->campaign_type) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $campaign->status === 'active' ? 'success' : ($campaign->status === 'pending' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($campaign->status) }}
                                        </span><br>
                                        <small class="badge bg-{{ $campaign->approval_status === 'approved' ? 'success' : ($campaign->approval_status === 'pending' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($campaign->approval_status) }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: {{ $campaign->total_views_requested > 0 ? ($campaign->views_completed / $campaign->total_views_requested) * 100 : 0 }}%">
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="ms-2 small">{{ $campaign->views_completed }}/{{ $campaign->total_views_requested }}</span>
                                        </div>
                                    </td>
                                    <td>${{ number_format($campaign->payment_per_view, 2) }}</td>
                                    <td>${{ number_format($campaign->total_budget, 2) }}</td>
                                    <td>
                                        <a href="{{ route('advertiser.campaigns.show', $campaign) }}" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                        <p class="text-muted mt-2">No campaigns yet. Create your first campaign to get started!</p>
                                        <a href="{{ route('advertiser.campaigns.create') }}" class="btn btn-primary">
                                            <i class="bi bi-plus-circle"></i> Create Campaign
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($campaigns->hasPages())
                    <div class="mt-3">
                        {{ $campaigns->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</x-app-layout>
