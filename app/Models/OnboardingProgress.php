<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProgress extends Model
{
    protected $table = 'user_onboarding_progress';

    protected $fillable = [
        'user_id',
        'user_type',
        'current_phase',
        'completed_phases',
        'phase_data',
        'is_completed',
        'completed_at',
        'skipped',
    ];

    protected $casts = [
        'completed_phases' => 'array',
        'phase_data' => 'array',
        'is_completed' => 'boolean',
        'skipped' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a specific phase is completed
     */
    public function isPhaseCompleted(string $phaseKey): bool
    {
        return in_array($phaseKey, $this->completed_phases ?? []);
    }

    /**
     * Get the list of incomplete phases
     */
    public function getIncompletePhases(array $allPhases): array
    {
        $completed = $this->completed_phases ?? [];
        return array_diff(array_keys($allPhases), $completed);
    }

    /**
     * Calculate progress percentage
     */
    public function getProgressPercentage(int $totalPhases): int
    {
        if ($totalPhases === 0) {
            return 100;
        }

        $completedCount = count($this->completed_phases ?? []);
        return (int) round(($completedCount / $totalPhases) * 100);
    }
}
