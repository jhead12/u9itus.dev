<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Standalone Citizen Controller
 *
 * Handles citizen-specific features in standalone mode. Campaign management,
 * billing, and analytics are added in a later phase (mirroring PoliticianController);
 * for now this exposes the dashboard landing page so newly registered citizens
 * have somewhere to land after registration/phone verification.
 */
class CitizenController extends Controller
{
    public function dashboard()
    {
        $user    = Auth::user();
        $citizen = $user->citizen;

        return view('standalone.citizen.dashboard', [
            'user'    => $user,
            'citizen' => $citizen,
        ]);
    }
}
