<?php

namespace App\Services\Auth;

use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class CompanyAccess
{
    public function isActiveUser(User $user): bool
    {
        return User::query()->whereKey($user->getKey())->where('status', UserStatus::Active)->exists();
    }

    public function permitsOperations(Company $company): bool
    {
        return $company->status === CompanyStatus::Approved;
    }

    public function membership(User $user, Company|int|string $company): ?CompanyMembership
    {
        if (! $this->isActiveUser($user)) {
            return null;
        }

        $query = $user->companyMemberships()->current()->with('company');

        if ($company instanceof Company || is_int($company)) {
            $query->where('company_id', $company instanceof Company ? $company->getKey() : $company);
        } else {
            // External selectors are UUIDs, never unvalidated numeric request IDs.
            $query->whereHas('company', fn ($query) => $query->where('uuid', $company));
        }

        return $query->first();
    }

    public function requireOperationalMembership(User $user, Company|int|string $company): CompanyMembership
    {
        $membership = $this->membership($user, $company);

        if (! $membership || ! $this->permitsOperations($membership->company)) {
            throw new AuthorizationException('Company access is not available.');
        }

        return $membership;
    }

    public function operationalMemberships(User $user): Collection
    {
        if (! $this->isActiveUser($user)) {
            return new Collection;
        }

        return $user->companyMemberships()->current()
            ->whereHas('company', fn ($query) => $query->approved())
            ->with('company')->orderBy('company_id')->get();
    }
}
