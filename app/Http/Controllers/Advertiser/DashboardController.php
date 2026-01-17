<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:advertiser']);
    }

    public function index()
    {
        $user = auth()->user();
        $advertiser = $user->advertiser;
        
        if (!$advertiser) {
            abort(403, 'Advertiser profile not found.');
        }
        
        $campaigns = $advertiser->campaigns()
            ->withCount(['assignments as total_views' => function($query) {
                $query->where('status', 'completed');
            }])
            ->latest()
            ->get();

        $stats = [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', 'active')->count(),
            'total_spent' => $advertiser->total_spent,
            'total_views' => $campaigns->sum('views_completed'),
        ];

        return view('advertiser.dashboard', compact('campaigns', 'stats'));
    }
}
