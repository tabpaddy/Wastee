<?php

namespace App\Services\Onboarding;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyProfileService
{
    public function update(User $actor, Company $company, array $input): Company
    {
        return DB::transaction(function () use ($actor, $company, $input): Company {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            app(CompanyOnboardingRequirements::class)->authorizeOwner($actor, $company, true);
            $company->update(Validator::make($input, CompanyOnboardingRequirements::profileRules($company))->validate());

            return $company;
        });
    }
}
