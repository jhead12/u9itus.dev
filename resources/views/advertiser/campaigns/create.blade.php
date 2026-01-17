@extends('layouts.app')

@section('title', 'Create Campaign')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h1>Create New Campaign</h1>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('advertiser.campaigns.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="title" class="form-label">Campaign Title *</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" 
                               id="title" name="title" value="{{ old('title') }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="campaign_type" class="form-label">Campaign Type *</label>
                        <select class="form-select @error('campaign_type') is-invalid @enderror" 
                                id="campaign_type" name="campaign_type" required>
                            <option value="">Select type...</option>
                            <option value="video" {{ old('campaign_type') === 'video' ? 'selected' : '' }}>Video</option>
                            <option value="audio" {{ old('campaign_type') === 'audio' ? 'selected' : '' }}>Audio</option>
                        </select>
                        @error('campaign_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="media_file" class="form-label">Media File *</label>
                        <input type="file" class="form-control @error('media_file') is-invalid @enderror" 
                               id="media_file" name="media_file" accept="video/*,audio/*" required>
                        <small class="text-muted">
                            Accepted formats: MP4, MOV, AVI, MP3, WAV (Max: 50MB)
                        </small>
                        @error('media_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_views_requested" class="form-label">Total Views Requested *</label>
                            <input type="number" class="form-control @error('total_views_requested') is-invalid @enderror" 
                                   id="total_views_requested" name="total_views_requested" 
                                   value="{{ old('total_views_requested') }}" min="1" required>
                            @error('total_views_requested')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="payment_per_view" class="form-label">Payment Per View ($) *</label>
                            <input type="number" class="form-control @error('payment_per_view') is-invalid @enderror" 
                                   id="payment_per_view" name="payment_per_view" 
                                   value="{{ old('payment_per_view', '1.00') }}" step="0.01" min="0.01" required>
                            @error('payment_per_view')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="min_watch_time_percent" class="form-label">
                            Minimum Watch Time (%)
                        </label>
                        <input type="number" class="form-control @error('min_watch_time_percent') is-invalid @enderror" 
                               id="min_watch_time_percent" name="min_watch_time_percent" 
                               value="{{ old('min_watch_time_percent', config('dial4dough.min_watch_time_percent')) }}" 
                               min="1" max="100">
                        <small class="text-muted">Viewers must watch at least this percentage to get paid (Default: {{ config('dial4dough.min_watch_time_percent') }}%)</small>
                        @error('min_watch_time_percent')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target States (Optional)</label>
                        <input type="text" class="form-control" name="target_states" placeholder="e.g., California, Texas, New York">
                        <small class="text-muted">Comma-separated list. Leave empty to target all states</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target Cities (Optional)</label>
                        <input type="text" class="form-control" name="target_cities" placeholder="e.g., Los Angeles, Dallas, New York City">
                        <small class="text-muted">Comma-separated list. Leave empty to target all cities</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('advertiser.campaigns.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Create Campaign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Pricing Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="fw-bold">Head Enterprises Fee:</label>
                    <p>{{ config('dial4dough.head_enterprises_fee_percent') }}%</p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Total Budget Calculation:</label>
                    <p class="small">
                        (Views × Payment per View) + {{ config('dial4dough.head_enterprises_fee_percent') }}% fee
                    </p>
                </div>
                <hr>
                <div id="budget-preview" class="alert alert-light">
                    <strong>Estimated Total Budget:</strong>
                    <h4 class="text-primary mb-0">$<span id="total-budget">0.00</span></h4>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-warning">
                <h6 class="mb-0">Campaign Guidelines</h6>
            </div>
            <div class="card-body">
                <ul class="small mb-0">
                    <li>Videos must be 10-20 seconds long</li>
                    <li>Content must be appropriate for all audiences</li>
                    <li>No misleading or false claims</li>
                    <li>All campaigns require admin approval</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const feePercent = {{ config('dial4dough.head_enterprises_fee_percent') }};
    
    function updateBudget() {
        const views = parseFloat(document.getElementById('total_views_requested').value) || 0;
        const paymentPerView = parseFloat(document.getElementById('payment_per_view').value) || 0;
        
        const viewCost = views * paymentPerView;
        const totalBudget = viewCost * (1 + (feePercent / 100));
        
        document.getElementById('total-budget').textContent = totalBudget.toFixed(2);
    }
    
    document.getElementById('total_views_requested').addEventListener('input', updateBudget);
    document.getElementById('payment_per_view').addEventListener('input', updateBudget);
    
    updateBudget();
</script>
@endpush
@endsection
