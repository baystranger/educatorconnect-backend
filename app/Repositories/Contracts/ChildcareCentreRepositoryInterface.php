<?php

namespace App\Repositories\Contracts;

use App\Models\ChildcareCentre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ChildcareCentreRepositoryInterface
{
    public function findForOwner(int $ownerId): ?ChildcareCentre;

    public function findPublicBySlug(string $slug): ?ChildcareCentre;

    public function search(array $filters): LengthAwarePaginator;

    public function create(array $data): ChildcareCentre;

    public function update(ChildcareCentre $centre, array $data): ChildcareCentre;
}
