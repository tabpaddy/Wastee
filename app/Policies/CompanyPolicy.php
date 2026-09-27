<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Services\Auth\CompanyAccess;

class CompanyPolicy
{
    public function __construct(private AuthorizationContext $context, private CompanyAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsPlatform($user, 'platform.companies.view')
            || $this->context->allowsCompany($user, 'company.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->context->allowsPlatform($user, 'platform.companies.view')
            || $this->context->allowsCompany($user, 'company.view', $company);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->context->allowsCompany($user, 'company.update', $company);
    }

    public function viewOnboarding(User $user, Company $company): bool
    {
        // Phase 3 can use this independently of operational approval or a panel.
        return $this->access->isActiveUser($user)
            && Company::query()->whereKey($company->getKey())->where('owner_user_id', $user->getKey())->exists();
    }
}
