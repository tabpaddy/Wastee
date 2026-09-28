<?php

namespace App\Services\Onboarding;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Services\Auth\CompanyAccess;
use App\Services\Auth\RoleProvisioner;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CompanyRegistrationService
{
    public function create(User $actor, array $input): Company
    {
        $previous = app(AuthorizationContext::class)->teamId();
        try {
            return DB::transaction(function () use ($actor, $input): Company {
                $owner = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless(app(CompanyAccess::class)->isActiveUser($owner) && $owner->hasVerifiedEmail(), 403);
                $data = Validator::make($input, CompanyOnboardingRequirements::profileRules())->validate();
                $company = Company::create([...$data, 'owner_user_id' => $owner->id,
                    'slug' => Str::slug($data['name']).'-'.Str::uuid7(), 'status' => CompanyStatus::Draft]);
                $ownerMembership = $company->memberships()->create(['user_id' => $owner->id, 'joined_at' => now()]);
                $ownerMembership->periods()->create(['joined_at' => $ownerMembership->joined_at,
                    'joined_by' => $owner->id, 'join_reason' => 'Company registration', 'joined_roles' => ['Owner']]);
                app(RoleProvisioner::class)->seedCompany($company);
                app(RoleProvisioner::class)->assignInitialOwner($company, $owner);
                $company->settings()->create([]);

                return $company;
            });
        } finally {
            $actor->unsetRelation('roles')->unsetRelation('permissions');
            setPermissionsTeamId($previous);
        }
    }
}
