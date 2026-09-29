<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class CommunityPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsPlatform($user, 'platform.geography.view');
    }

    public function view(User $user, Community $community): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->context->allowsPlatform($user, 'platform.geography.manage');
    }

    public function update(User $user, Community $community): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Community $community): bool
    {
        return false;
    }
}
