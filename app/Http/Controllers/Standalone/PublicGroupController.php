<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\NeighborhoodGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public-facing Neighborhood Groups pages — directory + group page.
 * No authentication required, mirrors PublicProfileController's split
 * from the authenticated GroupController/GroupMembershipController.
 */
class PublicGroupController extends Controller
{
    public function index(Request $request): View
    {
        $query = NeighborhoodGroup::query()->withCount('members');

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($state = $request->input('state')) {
            $query->where('state', $state);
        }
        if ($city = trim((string) $request->input('city', ''))) {
            $query->where('city', 'like', "%{$city}%");
        }
        if ($scope = $request->input('scope')) {
            $query->where('scope', $scope);
        }

        $groups = $query->orderBy('name')->paginate(24)->withQueryString();
        $states = config('u9itus.us_states', []);

        return view('standalone.public.groups-directory', compact('groups', 'states'));
    }

    public function show(NeighborhoodGroup $group, ?string $scope = null): View|RedirectResponse
    {
        $canonicalScope = $group->scopeUrlSegment();
        if ($canonicalScope !== null && $scope !== $canonicalScope) {
            return redirect()->route('groups.public.show', ['group' => $group, 'scope' => $canonicalScope], 301);
        }

        $group->loadCount('members');
        $user = auth()->user();
        $isMember = $group->isMember($user);
        $isOwner = $group->isOwner($user);
        $isAdmin = $group->isAdmin($user);
        $upcomingEvents = $group->events()->published()->orderBy('starts_at')->take(5)->get();

        return view('standalone.public.group-show', compact('group', 'isMember', 'isOwner', 'isAdmin', 'upcomingEvents'));
    }
}
