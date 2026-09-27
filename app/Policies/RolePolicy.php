<?php

namespace App\Policies;

use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Support\PermissionCatalogue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsCompany($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->belongsToContext($role) && $this->context->allowsCompany($user, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->context->allowsCompany($user, 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->belongsToContext($role) && $role->name !== 'Owner'
            && $this->context->allowsCompany($user, 'roles.update') && $this->canGrantContents($user, $role);
    }

    public function assign(User $user, Role $role, CompanyMembership $recipient): bool
    {
        return $this->belongsToContext($role) && $role->name !== 'Owner'
            && $recipient->company_id === $this->context->companyId()
            && $recipient->user_id !== $this->context->company()?->owner_user_id
            && CompanyMembership::query()->current()->whereKey($recipient->getKey())->exists()
            && $this->context->allowsCompany($user, 'roles.assign') && $this->canGrantContents($user, $role);
    }

    public function assignPermission(User $user, Role $role, Permission $permission): bool
    {
        return $this->update($user, $role) && $permission->guard_name === 'web'
            && in_array($permission->name, PermissionCatalogue::company(), true)
            && $this->context->allowsCompany($user, $permission->name);
    }

    public function delete(User $user, Role $role): bool
    {
        return false; // Role deletion and safe reassignment are deferred to staff-management workflows.
    }

    private function belongsToContext(Role $role): bool
    {
        return $role->company_id !== null && (int) $role->company_id > 0
            && (int) $role->company_id === $this->context->companyId() && $role->guard_name === 'web';
    }

    private function canGrantContents(User $user, Role $role): bool
    {
        return $role->permissions()->get()->every(fn (Permission $permission) => $permission->guard_name === 'web' && $this->context->allowsCompany($user, $permission->name));
    }
}
