<?php

namespace App\Services\Onboarding;

use App\Enums\CompanyLocationStatus;
use App\Models\Company;
use App\Models\CompanyLocation;
use App\Models\Lga;
use App\Models\User;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanyLocationService
{
    public function establish(User $actor, Company $company, array $input): CompanyLocation
    {
        return DB::transaction(function () use ($actor, $company, $input): CompanyLocation {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            app(CompanyOnboardingRequirements::class)->authorizeOwner($actor, $company, true);
            $data = Validator::make($input, CompanyOnboardingRequirements::locationRules())->validate();
            $lga = Lga::findOrFail($data['lga_id']);
            $old = $company->locations()->where('is_head_office', true)->whereNull('active_to')->first();
            if ($old) {
                if ($old->active_from->greaterThanOrEqualTo(today())) {
                    throw ValidationException::withMessages(['location' => 'A head office was established today. Replacement is available from tomorrow to preserve date history.']);
                }
                $old->update(['active_to' => today(), 'status' => CompanyLocationStatus::Closed]);
            }

            return $company->locations()->create([...$data, 'state_id' => $lga->state_id,
                'is_head_office' => true, 'active_from' => today()]);
        });
    }
}
