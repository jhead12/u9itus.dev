<?php

namespace App\Policies;

use App\Models\AdAssignment;
use App\Models\User;

class AdAssignmentPolicy
{
    public function view(User $user, AdAssignment $assignment)
    {
        return $user->id === $assignment->viewer_id;
    }

    public function complete(User $user, AdAssignment $assignment)
    {
        return $user->id === $assignment->viewer_id 
            && in_array($assignment->status, ['assigned', 'in_progress']);
    }
}
