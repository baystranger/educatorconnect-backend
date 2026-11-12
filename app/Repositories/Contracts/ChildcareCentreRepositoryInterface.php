<?php

namespace App\Repositories\Contracts;

use App\Models\ChildcareCentre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ChildcareCentreRepositoryInterface
{
    public function findForOwner(int $ownerId): ?ChildcareCentre;

    public function findPublicBySlug(string $slug): ?ChildcareCentre;

    public function search(array $filters): LengthAwarePaginator;

    /** Average coordinates of centres matching a typed city / postal code, or null. */
    public function originForLocation(string $location): ?array;

    public function create(array $data): ChildcareCentre;

    public function update(ChildcareCentre $centre, array $data): ChildcareCentre;
}
