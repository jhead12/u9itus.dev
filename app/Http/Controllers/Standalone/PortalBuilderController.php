<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationEndorsementRequest;
use App\Models\Organization;
use App\Models\OrganizationEndorsement;
use App\Services\PortalDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The org-owner-facing drag-and-drop portal builder (React + Puck, mounted
 * via resources/js/portal-builder/app.js — see that entry's docblock).
 * Authorization is OrganizationPolicy::update, NOT the Super Admin/staff
 * permission system — see that policy's docblock for why.
 */
class PortalBuilderController extends Controller
{
    public function __construct(protected PortalDataService $portalData)
    {
    }

    public function edit(Organization $organization)
    {
        Gate::authorize('update', $organization);

        return view('standalone.portal.builder', [
            'organization' => $organization,
            'portalData' => $this->portalData->forOrganization($organization),
            'orgTypes' => config('organizations.types'),
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        Gate::authorize('update', $organization);

        // A blank text input submits "" rather than absent/null, which
        // `nullable` alone doesn't treat as passing `size:2` — normalize
        // before validating so clearing the field in the settings form works.
        $request->merge([
            'target_state' => $request->filled('target_state') ? $request->input('target_state') : null,
            'target_district' => $request->filled('target_district') ? $request->input('target_district') : null,
        ]);

        // The Puck editor's autosave/publish always sends portal_layout; the
        // plain Blade settings form (state/district/publish toggle) never
        // does — each updates its own slice, so either can submit alone.
        $validated = $request->validate([
            'portal_layout' => ['sometimes', 'array'],
            'portal_published' => ['nullable', 'boolean'],
            'target_state' => ['nullable', 'string', 'size:2'],
            'target_district' => ['nullable', 'string', 'max:32'],
        ]);

        $targetState = $organization->target_state;
        if (array_key_exists('target_state', $validated)) {
            $targetState = is_string($validated['target_state']) ? strtoupper($validated['target_state']) : null;
        }

        // array_key_exists (not isset) so an intentional clear — the field
        // present but normalized to null above — still overwrites the old
        // value, while a request that never sent the field at all (autosave)
        // leaves it untouched.
        $organization->update([
            'portal_layout' => $validated['portal_layout'] ?? $organization->portal_layout,
            'portal_published' => $request->boolean('portal_published', $organization->portal_published),
            'target_state' => $targetState,
            'target_district' => array_key_exists('target_district', $validated) ? $validated['target_district'] : $organization->target_district,
        ]);

        // Autosave hits this same action in the background — same
        // JSON-vs-redirect split PostController::update() uses, so the
        // editor's autosave loop never has to navigate the page away.
        if ($request->wantsJson()) {
            return response()->json(['saved' => true]);
        }

        return redirect()
            ->route('portal.builder.edit', $organization)
            ->with('status', 'Portal saved.');
    }

    public function endorsements(Organization $organization)
    {
        Gate::authorize('update', $organization);

        return response()->json(
            $this->portalData->forOrganization($organization)['endorsements']
        );
    }

    public function storeEndorsement(StoreOrganizationEndorsementRequest $request, Organization $organization)
    {
        Gate::authorize('update', $organization);

        $endorsement = $organization->endorsements()->create($request->validated());

        if ($request->wantsJson()) {
            return response()->json($endorsement->load(['politician:id,full_name,slug,profile_photo_url', 'ballotMeasure:id,title,measure_number']), 201);
        }

        return redirect()
            ->route('portal.builder.edit', $organization)
            ->with('status', 'Endorsement added.');
    }

    public function destroyEndorsement(Organization $organization, OrganizationEndorsement $endorsement)
    {
        Gate::authorize('update', $organization);
        abort_unless($endorsement->organization_id === $organization->id, 404);

        $endorsement->delete();

        if (request()->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()
            ->route('portal.builder.edit', $organization)
            ->with('status', 'Endorsement removed.');
    }
}
