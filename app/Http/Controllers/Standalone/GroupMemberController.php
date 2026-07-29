<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\NeighborhoodGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Member list + admin actions (promote/demote, remove) for a Neighborhood
 * Group. Member-to-member visible, not public — see the class-level note
 * on index(). Routed inside the `groups.` prefix's `role:voter|citizen`
 * middleware, so a guest never reaches this controller at all.
 */
class GroupMemberController extends Controller
{
    /**
     * Members-only: guests/non-members only ever see the aggregate count
     * on the group's public page, not names/avatars — named exposure of
     * group membership is opt-in-by-visibility here, not default-on, per
     * doc/BELONGING_MEANING_IDENTITY_STRATEGY.md's self-asserted-content
     * principle.
     */
    public function index(NeighborhoodGroup $group): View
    {
        $user = Auth::user();
        abort_unless($group->isMember($user), 403, 'Only members can view the member list.');

        // 'admin' sorts before 'member' alphabetically, so this lists admins first.
        $members = $group->members()->orderBy('group_memberships.role')->orderBy('name')->get();
        $isOwner = $group->isOwner($user);
        $isAdmin = $group->isAdmin($user);

        return view('standalone.groups.members', compact('group', 'members', 'isOwner', 'isAdmin'));
    }

    /** Owner-only: promote a member to co-admin, or demote back to member. */
    public function updateRole(Request $request, NeighborhoodGroup $group, User $user): RedirectResponse
    {
        abort_unless($group->isOwner(Auth::user()), 403, 'Only the group creator can change member roles.');
        abort_if($group->isOwner($user), 422, "The group creator's role can't be changed.");
        abort_unless($group->isMember($user), 404);

        $request->validate(['role' => ['required', 'in:member,admin']]);

        $group->members()->updateExistingPivot($user->id, ['role' => $request->string('role')]);

        return back()->with('status', $user->name.' is now '.($request->string('role') === 'admin' ? 'an admin.' : 'a member.'));
    }

    /** Any admin (owner or co-admin) can remove a regular member; the
     *  owner can never be removed this way — only by deleting the group. */
    public function destroy(NeighborhoodGroup $group, User $user): RedirectResponse
    {
        abort_unless($group->isAdmin(Auth::user()), 403, 'Only a group admin can remove members.');
        abort_if($group->isOwner($user), 422, "The group creator can't be removed.");

        $group->members()->detach($user->id);

        return back()->with('status', $user->name.' was removed from the group.');
    }
}
