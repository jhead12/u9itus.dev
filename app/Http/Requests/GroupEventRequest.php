<?php

namespace App\Http\Requests;

/**
 * Validates a Neighborhood Group event — identical fields to
 * CivicEventRequest (no rule is host-specific), just with ownership
 * (group admin, not citizen/politician role) checked in the controller
 * instead of here, same pattern as UpdateNeighborhoodGroupRequest.
 */
class GroupEventRequest extends CivicEventRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
