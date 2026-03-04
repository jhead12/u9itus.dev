<?php

namespace App\Services;

use App\Models\Politician;
use App\Models\User;
use App\Mail\ProfileVerificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Profile Verification Service
 * 
 * Handles government email verification for politicians to enable
 * public data transparency features (Ballotpedia, OpenSecrets, etc.)
 * 
 * Government email domains: .gov, .mil, state legislature domains
 */
class ProfileVerificationService
{
    /**
     * List of valid government email domains
     * 
     * @var array
     */
    protected array $governmentDomains = [
        '.gov',
        '.mil',
        '.fed.us',
        // State legislature domains
        'legislature.ca.gov',
        'nysenate.gov',
        'ilga.gov',
        'legis.state.tx.us',
        'leg.wa.gov',
        'legislature.mi.gov',
        'legislature.ohio.gov',
        'ncleg.gov',
        'legislature.maine.gov',
        'flsenate.gov',
        'legis.iowa.gov',
        // Add more state-specific domains as needed
    ];

    /**
     * Check if email domain is a government domain
     * 
     * @param string $email
     * @return bool
     */
    public function isGovernmentEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, "@"), 1));

        // Check if ends with common gov domains
        foreach ($this->governmentDomains as $govDomain) {
            if (str_ends_with($domain, $govDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initiate verification process
     * 
     * @param Politician $politician
     * @param string $governmentEmail
     * @return bool
     * @throws \Exception
     */
    public function initiateVerification(Politician $politician, string $governmentEmail): bool
    {
        // Validate government email
        if (!$this->isGovernmentEmail($governmentEmail)) {
            throw new \Exception('Email must be from a government domain (.gov, .mil, or official state legislature domain)');
        }

        // Check if email is already verified by another politician
        $existing = Politician::where('verification_email', $governmentEmail)
            ->where('verification_status', 'verified')
            ->where('id', '!=', $politician->id)
            ->first();

        if ($existing) {
            throw new \Exception('This government email is already verified by another profile');
        }

        // Generate verification token
        $token = Str::random(64);

        // Update politician record
        $politician->update([
            'verification_status' => 'pending',
            'verification_email' => $governmentEmail,
            'verification_token' => $token,
            'verified_at' => null,
        ]);

        // Send verification email
        try {
            Mail::to($governmentEmail)->send(new ProfileVerificationMail($politician, $token));
        } catch (\Exception $e) {
            // Rollback on email failure
            $politician->update([
                'verification_status' => 'unverified',
                'verification_email' => null,
                'verification_token' => null,
            ]);
            
            throw new \Exception('Failed to send verification email: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Verify the token and complete verification
     * 
     * @param string $token
     * @return Politician|null
     */
    public function verifyToken(string $token): ?Politician
    {
        $politician = Politician::where('verification_token', $token)
            ->where('verification_status', 'pending')
            ->first();

        if (!$politician) {
            return null;
        }

        // Mark as verified
        $politician->update([
            'verification_status' => 'verified',
            'verified_at' => Carbon::now(),
            'verification_token' => null, // Clear token after use
        ]);

        return $politician;
    }

    /**
     * Revoke verification (admin action or politician request)
     * 
     * @param Politician $politician
     * @return bool
     */
    public function revokeVerification(Politician $politician): bool
    {
        $politician->update([
            'verification_status' => 'unverified',
            'verification_email' => null,
            'verified_at' => null,
            'verification_token' => null,
            // Disable all data sources
            'show_ballotpedia_data' => false,
            'show_opensecrets_data' => false,
            'show_votesmart_data' => false,
            'show_fec_data' => false,
        ]);

        return true;
    }

    /**
     * Check if politician can enable transparency features
     * 
     * @param Politician $politician
     * @return bool
     */
    public function canEnableTransparency(Politician $politician): bool
    {
        return $politician->verification_status === 'verified';
    }

    /**
     * Get verification status for display
     * 
     * @param Politician $politician
     * @return array
     */
    public function getVerificationStatus(Politician $politician): array
    {
        $status = $politician->verification_status;

        $statusConfig = [
            'unverified' => [
                'label' => 'Not Verified',
                'color' => 'gray',
                'description' => 'Verify your profile with a government email to enable public data transparency features',
                'action' => 'Start Verification',
            ],
            'pending' => [
                'label' => 'Verification Pending',
                'color' => 'yellow',
                'description' => 'Check your government email (' . $politician->verification_email . ') for the verification link',
                'action' => 'Resend Email',
            ],
            'verified' => [
                'label' => 'Verified',
                'color' => 'green',
                'description' => 'Your profile is verified. You can now enable public data transparency features.',
                'action' => null,
                'verified_at' => $politician->verified_at?->format('M d, Y'),
            ],
        ];

        return $statusConfig[$status] ?? $statusConfig['unverified'];
    }

    /**
     * Get list of government domains for frontend validation
     * 
     * @return array
     */
    public function getGovernmentDomains(): array
    {
        return $this->governmentDomains;
    }
}
