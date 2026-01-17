@extends('layouts.app')

@section('title', 'Advertiser Dashboard')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h1>Advertiser Dashboard</h1>
        <hr>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h6 class="card-title">Total Campaigns</h6>
                <h2>{{ $stats['total_campaigns'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h6 class="card-title">Active Campaigns</h6>
                <h2>{{ $stats['active_campaigns'] }}</h2>
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
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h6 class="card-title">Total Spent</h6>
                <h2>${{ number_format($stats['total_spent'], 2) }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <a href="{{ route('advertiser.campaigns.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Campaign
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">My Campaigns</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Budget</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($campaigns as $campaign)
                                <tr>
                                    <td>
                                        <strong>{{ $campaign->title }}</strong><br>
                                        <small class="text-muted">{{ Str::limit($campaign->description, 50) }}</small>
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
                                        <strong>{{ $campaign->views_completed }}</strong> / {{ $campaign->total_views_requested }}
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: {{ $campaign->total_views_requested > 0 ? ($campaign->views_completed / $campaign->total_views_requested) * 100 : 0 }}%">
                                            </div>
                                        </div>
                                    </td>
                                    <td>${{ number_format($campaign->total_budget, 2) }}</td>
                                    <td>{{ $campaign->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('advertiser.campaigns.show', $campaign) }}" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        No campaigns yet. <a href="{{ route('advertiser.campaigns.create') }}">Create your first campaign</a>
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
