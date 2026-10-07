<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function update(User $user, Job $job): bool
    {
        return $user->hasRole('administrator')
            || (int) $job->centre?->owner_user_id === (int) $user->id;
    }

    public function manage(User $user, Job $job): bool
    {
        return $this->update($user, $job);
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->update($user, $job);
    }

    public function apply(User $user, Job $job): bool
    {
        return $user->isEducator() && $job->isAcceptingApplications();
    }
}
