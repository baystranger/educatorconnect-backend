<?php

namespace App\Services;

use App\Models\ChildcareCentre;
use App\Models\User;
use App\Support\PersonName;

/**
 * Keeps a centre account's names consistent across `users` and `childcare_centres`:
 *
 *  - users.name is the display name of a centre account, i.e. the name of its first centre.
 *  - users.first_name/last_name is the person who signs in; childcare_centres.contact_person
 *    is the public contact. They start out as the same person. Renaming the account holder
 *    updates a contact person that still matches; editing the contact person never renames
 *    the account holder (a centre may list its director instead).
 */
class CentreAccountNameSync
{
    /** The centre was renamed: the account's display name follows its first centre. */
    public function centreRenamed(ChildcareCentre $centre): void
    {
        $owner = $centre->owner()->first();
        if ($owner && (int) $owner->childcareCentres()->oldest('id')->value('id') === (int) $centre->id) {
            $owner->forceFill(['name' => $centre->name])->save();
        }
    }

    /** The holder was renamed from $previous: centres whose contact person matched follow. */
    public function holderRenamed(User $user, string $previous): void
    {
        $current = PersonName::join($user->first_name, $user->last_name);
        if ($current === '' || $current === $previous) {
            return;
        }

        $user->childcareCentres()->get()
            ->filter(fn (ChildcareCentre $centre) => blank($centre->contact_person) || trim($centre->contact_person) === $previous)
            ->each(fn (ChildcareCentre $centre) => $centre->update(['contact_person' => $current]));
    }
}
