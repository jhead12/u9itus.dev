<?php

namespace App\Http\Controllers\Api;

use App\Models\Politician;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only endpoint for civic office profile data.
 * No authentication required — voters need this to understand
 * what each candidate's role does and how it affects their lives.
 */
class OfficeProfileController
{
    /**
     * GET /api/v1/politicians/{politician:uuid}/office-profile
     *
     * Returns structured civic info about the politician's office.
     * Returns 204 (no content) if no profile has been added yet so the
     * voter UI can gracefully hide the popup trigger.
     */
    public function show(Politician $politician): JsonResponse
    {
        $profile = $politician->officeProfile;

        if (! $profile) {
            return response()->json(['message' => 'No office profile available yet.'], 404);
        }

        return response()->json([
            'politician' => [
                'uuid'             => $politician->uuid,
                'full_name'        => $politician->full_name,
                'political_office' => $politician->political_office,
                'governance_level' => $politician->governance_level,
                'state'            => $politician->state,
                'city'             => $politician->city,
                'party_affiliation' => $politician->party_affiliation,
            ],
            'office_profile' => $profile->toVoterPayload(),
        ]);
    }
}
