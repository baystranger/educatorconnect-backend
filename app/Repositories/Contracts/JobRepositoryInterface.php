<?php

namespace App\Repositories\Contracts;

use App\Models\Job;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface JobRepositoryInterface
{
    public function search(array $filters, ?User $viewer = null): LengthAwarePaginator;

    /** Counts of matching published jobs per employment type, ignoring the type filter. */
    public function employmentTypeFacets(array $filters): array;

    public function findPublic(string $slug, ?User $viewer = null): ?Job;

    public function create(array $data): Job;

    public function update(Job $job, array $data): Job;
}
