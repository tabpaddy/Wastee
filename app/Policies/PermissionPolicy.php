<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Support\PermissionCatalogue;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsCompany($user, 'roles.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $permission->guard_name === 'web'
            && in_array($permission->name, PermissionCatalogue::company(), true)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false; // Permission definitions are a system catalogue, not company-owned records.
    }

    public function update(User $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }
}
