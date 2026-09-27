<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

class PanelAccess
{
    public function __construct(private CompanyAccess $access, private AuthorizationContext $context) {}

    public function platform(User $user): bool
    {
        return $this->access->isActiveUser($user)
            && $this->context->runForPlatform($user, fn () => $this->context->allowsPlatform($user, 'platform.access'));
    }

    public function company(User $user, Company $company): bool
    {
        $membership = $this->access->membership($user, $company);

        return $membership && $this->access->permitsOperations($membership->company)
            && $this->context->runForCompany($user, $company, fn () => $this->context->allowsCompany($user, 'company.access'));
    }

    public function companies(User $user): Collection
    {
        return $this->access->operationalMemberships($user)
            ->map(fn ($membership) => $membership->company)
            ->filter(fn (Company $company) => $this->company($user, $company))->values();
    }
}
