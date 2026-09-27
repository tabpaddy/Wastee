<?php

namespace App\Policies;

use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class CompanyMembershipPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsCompany($user, 'staff.view');
    }

    public function view(User $user, CompanyMembership $membership): bool
    {
        return $membership->company_id === $this->context->companyId()
            && $this->context->allowsCompany($user, 'staff.view');
    }

    public function create(User $user): bool
    {
        return $this->context->allowsCompany($user, 'staff.create');
    }

    public function update(User $user, CompanyMembership $membership): bool
    {
        return $this->isManageable($membership) && $this->context->allowsCompany($user, 'staff.update');
    }

    public function deactivate(User $user, CompanyMembership $membership): bool
    {
        return $this->isManageable($membership) && $membership->user_id !== $user->getKey()
            && $this->context->allowsCompany($user, 'staff.deactivate');
    }

    private function isManageable(CompanyMembership $membership): bool
    {
        return $membership->company_id === $this->context->companyId()
            && $membership->user_id !== $this->context->company()?->owner_user_id;
    }
}
