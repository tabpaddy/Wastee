<?php

namespace App\Services\Onboarding;

use App\Enums\ApprovalAction;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Support\Facades\DB;

class CompanyReviewSubmissionService
{
    public function submit(User $actor, Company $company): void
    {
        DB::transaction(function () use ($actor, $company): void {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $requirements = app(CompanyOnboardingRequirements::class);
            $requirements->authorizeOwner($actor, $company, true);
            $requirements->assertComplete($company);
            $from = $company->status;
            $company->update(['status' => CompanyStatus::PendingReview, 'submitted_at' => now()]);
            $company->approvalLogs()->create(['actor_user_id' => $actor->id, 'action' => ApprovalAction::Submitted,
                'from_status' => $from, 'to_status' => CompanyStatus::PendingReview]);
        });
    }
}
