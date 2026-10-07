<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByOAuthIdentity(string $provider, string $providerId): ?User
    {
        return User::query()
            ->whereHas('socialAccounts', fn ($q) => $q->where('provider', $provider)->where('provider_user_id', $providerId))
            ->orWhere(fn ($q) => $q->where('oauth_provider', $provider)->where('oauth_provider_id', $providerId))
            ->first();
    }
}
