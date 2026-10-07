<?php

namespace App\Policies;

use App\Models\ParentRequest;
use App\Models\User;

class ParentRequestPolicy
{
    public function manage(User $user, ParentRequest $request): bool
    {
        return $user->hasRole('administrator')
            || (int) $request->centre?->owner_user_id === (int) $user->id;
    }
}
