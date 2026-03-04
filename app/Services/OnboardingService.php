<?php

namespace App\Services;

use App\Models\OnboardingProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    /**
     * Voter onboarding phases
     */
    public const VOTER_PHASES = [
        'welcome' => [
            'title' => 'Welcome to U9itus',
            'description' => 'Complete your profile and get started earning',
            'route' => 'voter.onboarding.welcome',
            'required' => true,
        ],
        'profile_setup' => [
            'title' => 'Complete Your Profile',
            'description' => 'Add your location and demographic information',
            'route' => 'voter.onboarding.profile',
            'required' => true,
        ],
        'first_watch' => [
            'title' => 'Watch Your First Ad',
            'description' => 'Learn how to watch political messages and earn money',
            'route' => 'voter.onboarding.first-watch',
            'required' => false,
        ],
        'payout_setup' => [
            'title' => 'Set Up Payouts',
            'description' => 'Add your PayPal or CashApp to receive earnings',
            'route' => 'voter.onboarding.payout',
            'required' => false,
        ],
        'referral_setup' => [
            'title' => 'Get Your Referral Links',
            'description' => 'Earn recurring commissions by referring voters and politicians',
            'route' => 'voter.onboarding.referrals',
            'required' => false,
        ],
    ];

    /**
     * Politician onboarding phases
     */
    public const POLITICIAN_PHASES = [
        'welcome' => [
            'title' => 'Welcome to U9itus',
            'description' => 'Set up your political profile and start your first campaign',
            'route' => 'politician.onboarding.welcome',
            'required' => true,
        ],
        'political_profile' => [
            'title' => 'Complete Political Profile',
            'description' => 'Add your office, district, party affiliation, and bio',
            'route' => 'politician.onboarding.profile',
            'required' => true,
        ],
        'payment_method' => [
            'title' => 'Add Payment Method',
            'description' => 'Connect Stripe for campaign billing',
            'route' => 'politician.onboarding.payment',
            'required' => true,
        ],
        'first_campaign' => [
            'title' => 'Create Your First Campaign',
            'description' => 'Upload your video message and set targeting',
            'route' => 'politician.onboarding.campaign',
            'required' => false,
        ],
        'add_credits' => [
            'title' => 'Add Credits & Go Live',
            'description' => 'Fund your account to start reaching voters',
            'route' => 'politician.onboarding.credits',
            'required' => false,
        ],
    ];

    /**
     * Admin onboarding phases
     */
    public const ADMIN_PHASES = [
        'welcome' => [
            'title' => 'Welcome to Admin Portal',
            'description' => 'Learn the essential admin workflows',
            'route' => 'admin.onboarding.welcome',
            'required' => true,
        ],
        'campaign_approval' => [
            'title' => 'Campaign Approval Tutorial',
            'description' => 'Learn how to review and approve campaigns',
            'route' => 'admin.onboarding.campaigns',
            'required' => false,
        ],
        'fraud_management' => [
            'title' => 'Fraud Management Overview',
            'description' => 'Monitor fraud signals and manage voter trust scores',
            'route' => 'admin.onboarding.fraud',
            'required' => false,
        ],
        'payout_processing' => [
            'title' => 'Payout Processing Tutorial',
            'description' => 'Process batch payouts and manage withdrawals',
            'route' => 'admin.onboarding.payouts',
            'required' => false,
        ],
    ];

    /**
     * Get or create onboarding progress for a user
     */
    public function getOrCreate(User $user, string $userType): OnboardingProgress
    {
        return OnboardingProgress::firstOrCreate(
            ['user_id' => $user->id],
            [
                'user_type' => $userType,
                'current_phase' => 'welcome',
                'completed_phases' => [],
                'phase_data' => [],
            ]
        );
    }

    /**
     * Get phases for a specific user type
     */
    public function getPhasesForType(string $userType): array
    {
        return match ($userType) {
            'voter' => self::VOTER_PHASES,
            'politician' => self::POLITICIAN_PHASES,
            'admin' => self::ADMIN_PHASES,
            default => [],
        };
    }

    /**
     * Mark a phase as completed and advance to next phase
     */
    public function completePhase(OnboardingProgress $progress, string $phaseKey, array $phaseData = []): void
    {
        $allPhases = $this->getPhasesForType($progress->user_type);
        $phaseKeys = array_keys($allPhases);

        // Get current completed phases
        $completedPhases = $progress->completed_phases ?? [];

        // Add this phase to completed if not already there
        if (!in_array($phaseKey, $completedPhases)) {
            $completedPhases[] = $phaseKey;
        }

        // Store phase-specific data
        $existingPhaseData = $progress->phase_data ?? [];
        $existingPhaseData[$phaseKey] = array_merge($existingPhaseData[$phaseKey] ?? [], $phaseData);

        // Find next incomplete phase
        $nextPhase = null;
        foreach ($phaseKeys as $key) {
            if (!in_array($key, $completedPhases)) {
                $nextPhase = $key;
                break;
            }
        }

        // Update progress
        $progress->update([
            'completed_phases' => $completedPhases,
            'phase_data' => $existingPhaseData,
            'current_phase' => $nextPhase ?? $phaseKey,
            'is_completed' => $nextPhase === null,
            'completed_at' => $nextPhase === null ? now() : null,
        ]);
    }

    /**
     * Skip onboarding entirely
     */
    public function skipOnboarding(OnboardingProgress $progress): void
    {
        $progress->update([
            'skipped' => true,
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if onboarding is required for this user
     */
    public function isOnboardingRequired(User $user, string $userType): bool
    {
        $progress = OnboardingProgress::where('user_id', $user->id)->first();

        if (!$progress) {
            return true; // New user needs onboarding
        }

        if ($progress->is_completed || $progress->skipped) {
            return false; // Already completed or skipped
        }

        // Check if there are any required phases incomplete
        $allPhases = $this->getPhasesForType($userType);
        $completedPhases = $progress->completed_phases ?? [];

        foreach ($allPhases as $key => $phase) {
            if ($phase['required'] && !in_array($key, $completedPhases)) {
                return true; // Has incomplete required phases
            }
        }

        return false;
    }

    /**
     * Get next required phase that needs completion
     */
    public function getNextRequiredPhase(OnboardingProgress $progress): ?string
    {
        $allPhases = $this->getPhasesForType($progress->user_type);
        $completedPhases = $progress->completed_phases ?? [];

        foreach ($allPhases as $key => $phase) {
            if ($phase['required'] && !in_array($key, $completedPhases)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Get onboarding stats
     */
    public function getOnboardingStats(): array
    {
        return [
            'voter' => [
                'total' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'voter')
                    ->count(),
                'completed' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'voter')
                    ->where('is_completed', true)
                    ->count(),
                'in_progress' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'voter')
                    ->where('is_completed', false)
                    ->where('skipped', false)
                    ->count(),
            ],
            'politician' => [
                'total' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'politician')
                    ->count(),
                'completed' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'politician')
                    ->where('is_completed', true)
                    ->count(),
                'in_progress' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'politician')
                    ->where('is_completed', false)
                    ->where('skipped', false)
                    ->count(),
            ],
            'admin' => [
                'total' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'admin')
                    ->count(),
                'completed' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'admin')
                    ->where('is_completed', true)
                    ->count(),
                'in_progress' => DB::table('user_onboarding_progress')
                    ->where('user_type', 'admin')
                    ->where('is_completed', false)
                    ->where('skipped', false)
                    ->count(),
            ],
        ];
    }
}
