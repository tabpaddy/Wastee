<?php

namespace App\Services\Onboarding;

use App\Enums\ApprovalAction;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanyApprovalService
{
    public function approve(User $actor, Company $company): void
    {
        $this->decide($actor, $company, CompanyStatus::Approved, ApprovalAction::Approved);
    }

    public function requestCorrection(User $actor, Company $company, string $reason): void
    {
        $this->decide($actor, $company, CompanyStatus::CorrectionRequired, ApprovalAction::CorrectionRequested, $reason);
    }

    public function reject(User $actor, Company $company, string $reason): void
    {
        $this->decide($actor, $company, CompanyStatus::Rejected, ApprovalAction::Rejected, $reason);
    }

    private function decide(User $actor, Company $company, CompanyStatus $to, ApprovalAction $action, ?string $reason = null): void
    {
        DB::transaction(function () use ($actor, $company, $to, $action, $reason): void {
            Gate::forUser($actor)->authorize('platform.companies.review');
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            if ($company->status !== CompanyStatus::PendingReview) {
                throw ValidationException::withMessages(['company' => 'Only pending applications can be reviewed.']);
            }
            if ($to === CompanyStatus::Approved) {
                app(CompanyOnboardingRequirements::class)->assertComplete($company, true);
            } else {
                $reason = trim($reason ?? '');
                Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:10', 'max:4000']])->validate();
            }
            $changes = ['status' => $to, 'review_summary' => $reason];
            if ($to === CompanyStatus::Approved) {
                $changes += ['approved_by' => $actor->id, 'approved_at' => now()];
            }
            $company->update($changes);
            $company->approvalLogs()->create(['actor_user_id' => $actor->id, 'action' => $action,
                'from_status' => CompanyStatus::PendingReview, 'to_status' => $to, 'remarks' => $reason]);
        });
    }
}
