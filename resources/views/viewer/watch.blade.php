<x-app-layout title="Watch Ad">

    <x-slot name="header">
        <h1 class="h3 mb-0">Watch Ad</h1>
    </x-slot>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-play-circle"></i> {{ $assignment->campaign->title }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        @if($assignment->campaign->campaign_type === 'video')
                            <div class="ratio ratio-16x9">
                                <video id="adVideo" class="w-100" controls>
                                    <source src="{{ Storage::url($assignment->campaign->media_file_url) }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        @elseif($assignment->campaign->campaign_type === 'audio')
                            <div class="text-center p-5 bg-light rounded">
                                <i class="bi bi-music-note-beamed" style="font-size: 5rem; color: #ccc;"></i>
                                <audio id="adVideo" class="w-100 mt-3" controls>
                                    <source src="{{ Storage::url($assignment->campaign->media_file_url) }}" type="audio/mpeg">
                                    Your browser does not support the audio tag.
                                </audio>
                            </div>
                        @endif

                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>Watch Progress</h5>
                                <span id="timer" class="badge bg-info">0s / {{ $assignment->campaign->media_duration }}s</span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div id="watchProgress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 0%">
                                    <span id="progressText">0%</span>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle"></i> You must watch at least {{ $assignment->campaign->min_watch_time_percent }}% to earn ${{ number_format($assignment->campaign->payment_per_view, 2) }}
                            </small>
                        </div>

                        <form method="POST" action="{{ route('viewer.complete', $assignment) }}" id="completeForm">
                            @csrf
                            <input type="hidden" name="watch_time" id="watchTime" value="0">
                            <button type="submit" id="completeBtn" class="btn btn-success btn-lg w-100 mt-4" disabled>
                                <i class="bi bi-check-circle"></i> Mark as Complete
                            </button>
                        </form>
                    </div>

                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Assignment Details</h5>
                                
                                <div class="mb-3">
                                    <label class="fw-bold">Campaign:</label>
                                    <p>{{ $assignment->campaign->title }}</p>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold">Description:</label>
                                    <p class="small">{{ $assignment->campaign->description }}</p>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold">Payment:</label>
                                    <p class="text-success h4">${{ number_format($assignment->campaign->payment_per_view, 2) }}</p>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold">Duration:</label>
                                    <p>{{ $assignment->campaign->media_duration }} seconds</p>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold">Required Watch Time:</label>
                                    <p>{{ $assignment->campaign->min_watch_time_percent }}%</p>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold">Expires:</label>
                                    <p class="text-warning">{{ $assignment->expires_at->diffForHumans() }}</p>
                                </div>

                                <hr>

                                <div class="alert alert-info small">
                                    <strong><i class="bi bi-lightbulb"></i> Tips:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Watch the entire ad to maximize earnings</li>
                                        <li>Don't skip or fast-forward</li>
                                        <li>Keep the volume audible</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="{{ route('viewer.dashboard') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const video = document.getElementById('adVideo');
    const watchProgress = document.getElementById('watchProgress');
    const progressText = document.getElementById('progressText');
    const timer = document.getElementById('timer');
    const completeBtn = document.getElementById('completeBtn');
    const watchTimeInput = document.getElementById('watchTime');
    const completeForm = document.getElementById('completeForm');
    
    const mediaDuration = {{ $assignment->campaign->media_duration }};
    const minWatchPercent = {{ $assignment->campaign->min_watch_time_percent }};
    
    let watchedSeconds = 0;
    let trackingInterval = null;
    
    video.addEventListener('play', function() {
        if (!trackingInterval) {
            trackingInterval = setInterval(function() {
                watchedSeconds++;
                updateProgress();
            }, 1000);
        }
    });
    
    video.addEventListener('pause', function() {
        if (trackingInterval) {
            clearInterval(trackingInterval);
            trackingInterval = null;
        }
    });
    
    video.addEventListener('ended', function() {
        if (trackingInterval) {
            clearInterval(trackingInterval);
            trackingInterval = null;
        }
        watchedSeconds = Math.max(watchedSeconds, mediaDuration);
        updateProgress();
    });
    
    function updateProgress() {
        const percentage = Math.min((watchedSeconds / mediaDuration) * 100, 100);
        
        watchProgress.style.width = percentage + '%';
        progressText.textContent = Math.round(percentage) + '%';
        timer.textContent = watchedSeconds + 's / ' + mediaDuration + 's';
        watchTimeInput.value = watchedSeconds;
        
        if (percentage >= minWatchPercent) {
            completeBtn.disabled = false;
            completeBtn.classList.remove('btn-secondary');
            completeBtn.classList.add('btn-success');
            watchProgress.classList.remove('progress-bar-animated');
            watchProgress.classList.add('bg-success');
        }
    }
    
    completeForm.addEventListener('submit', function(e) {
        if (watchedSeconds < (mediaDuration * minWatchPercent / 100)) {
            e.preventDefault();
            alert('Please watch at least ' + minWatchPercent + '% of the ad to complete this assignment.');
            return false;
        }
    });
    
    window.addEventListener('beforeunload', function (e) {
        if (watchedSeconds > 0 && watchedSeconds < mediaDuration) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
</script>
@endpush
</x-app-layout>
