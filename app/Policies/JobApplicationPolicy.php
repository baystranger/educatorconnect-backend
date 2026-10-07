<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    /** The applicant themself. */
    public function own(User $user, JobApplication $application): bool
    {
        return (int) $application->educator_id === (int) $user->id;
    }

    /** The centre that owns the posting (or an administrator). */
    public function review(User $user, JobApplication $application): bool
    {
        return $user->hasRole('administrator')
            || (int) $application->job?->centre?->owner_user_id === (int) $user->id;
    }

    public function view(User $user, JobApplication $application): bool
    {
        return $this->own($user, $application) || $this->review($user, $application);
    }
}
