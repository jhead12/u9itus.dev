<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\PortalDataService;

/**
 * Public render of a client's white-label portal. Path-based for now
 * (u9itus.com/portal/{slug}) rather than a custom domain/subdomain — see
 * the branch plan's "no custom domains yet" scope note.
 */
class PortalController extends Controller
{
    public function __construct(protected PortalDataService $portalData)
    {
    }

    public function show(Organization $organization)
    {
        abort_unless($organization->portal_published, 404);

        return view('standalone.portal.show', [
            'organization' => $organization,
            'portalData' => $this->portalData->forOrganization($organization),
            'embed' => false,
        ]);
    }

    public function embed(Organization $organization)
    {
        abort_unless($organization->portal_published, 404);

        return response()
            ->view('standalone.portal.show', [
                'organization' => $organization,
                'portalData' => $this->portalData->forOrganization($organization),
                'embed' => true,
            ])
            // Deliberately permissive: this page exists to be iframed on a
            // client's own WordPress/Squarespace site (the flyer's "embed
            // widget" promise) — see the branch plan's embed note.
            ->withHeaders(['Content-Security-Policy' => 'frame-ancestors *']);
    }
}
