<?php

namespace App\Policies;

use App\Models\ChildcareCentre;
use App\Models\User;

class ChildcareCentrePolicy
{
    public function update(User $user, ChildcareCentre $centre): bool
    {
        return $user->hasRole('administrator') || (int) $centre->owner_user_id === (int) $user->id;
    }

    public function manage(User $user, ChildcareCentre $centre): bool
    {
        return $this->update($user, $centre);
    }
}
