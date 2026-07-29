<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\NeighborhoodGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Join/leave a Neighborhood Group. Full-page form submissions from
 * /groups/{slug}, not an AJAX widget — plain redirect responses.
 */
class GroupMembershipController extends Controller
{
    public function store(Request $request, NeighborhoodGroup $group): RedirectResponse
    {
        $user = $request->user();

        if (! $group->isMember($user)) {
            $group->members()->attach($user->id, [
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        return back()->with('status', 'You joined '.$group->name.'.');
    }

    public function destroy(Request $request, NeighborhoodGroup $group): RedirectResponse
    {
        $user = $request->user();

        if ($group->admin_user_id === $user->id) {
            return back()->withErrors(['group' => 'The group creator cannot leave. Delete the group instead.']);
        }

        $group->members()->detach($user->id);

        return back()->with('status', 'You left '.$group->name.'.');
    }
}
