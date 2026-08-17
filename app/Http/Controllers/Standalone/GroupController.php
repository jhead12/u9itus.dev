<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNeighborhoodGroupRequest;
use App\Http\Requests\UpdateNeighborhoodGroupRequest;
use App\Models\NeighborhoodGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Authenticated group creation + admin settings. Creation is voter- and
 * citizen-only (see StoreNeighborhoodGroupRequest::authorize() and the
 * routes/standalone.php `role:voter|citizen` middleware) — politicians
 * cannot create groups. Editing is open to the owner and any promoted
 * co-admin (NeighborhoodGroup::isAdmin()); deleting is owner-only
 * (NeighborhoodGroup::isOwner()) so a co-admin can't dissolve the group
 * out from under its founder.
 */
class GroupController extends Controller
{
    public function create(): View
    {
        return view('standalone.groups.create');
    }

    public function store(StoreNeighborhoodGroupRequest $request): RedirectResponse
    {
        $group = NeighborhoodGroup::create([
            ...$request->validated(),
            'admin_user_id' => $request->user()->id,
        ]);

        $group->members()->attach($request->user()->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        return redirect()
            ->route('groups.public.show', $group)
            ->with('status', 'Group created.');
    }

    public function edit(NeighborhoodGroup $group): View
    {
        $this->authorizeGroupAdmin($group);

        return view('standalone.groups.edit', [
            'group' => $group,
            'isOwner' => $group->isOwner(Auth::user()),
        ]);
    }

    public function update(UpdateNeighborhoodGroupRequest $request, NeighborhoodGroup $group): RedirectResponse
    {
        $this->authorizeGroupAdmin($group);

        $group->update($request->validated());

        return redirect()
            ->route('groups.public.show', $group)
            ->with('status', 'Group settings updated.');
    }

    public function destroy(NeighborhoodGroup $group): RedirectResponse
    {
        $this->authorizeGroupOwner($group);

        $group->delete();

        return redirect()
            ->route('groups.directory')
            ->with('status', 'Group deleted.');
    }

    private function authorizeGroupAdmin(NeighborhoodGroup $group): void
    {
        abort_unless($group->isAdmin(Auth::user()), 403, 'Only a group admin can manage this group.');
    }

    private function authorizeGroupOwner(NeighborhoodGroup $group): void
    {
        abort_unless($group->isOwner(Auth::user()), 403, 'Only the group creator can delete this group.');
    }
}
