<?php

namespace App\Services\Operations;

use App\Enums\ServiceAreaStatus;
use App\Models\Community;
use App\Models\Company;
use App\Models\CompanyServiceArea;
use App\Models\Property;
use App\Models\PropertyCompanyAssignment;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanyServiceAreaService
{
    public function __construct(private AuthorizationContext $context) {}

    public function add(User $actor, string $communityUuid): CompanyServiceArea
    {
        return DB::transaction(function () use ($actor, $communityUuid): CompanyServiceArea {
            Gate::forUser($actor)->authorize('create', CompanyServiceArea::class);
            $company = Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $community = Community::query()->active()->where('uuid', $communityUuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', CompanyServiceArea::class);
            if ($company->serviceAreas()->where('community_id', $community->id)->overlapping(today()->toDateString())->exists()) {
                throw ValidationException::withMessages(['community_uuid' => 'Coverage already exists or conflicts with a future period.']);
            }

            return $company->serviceAreas()->create(['community_id' => $community->id,
                'active_from' => today(), 'status' => ServiceAreaStatus::Active, 'created_by' => $actor->id]);
        });
    }

    public function close(User $actor, CompanyServiceArea $area, string $reason): void
    {
        DB::transaction(function () use ($actor, $area, $reason): void {
            Gate::forUser($actor)->authorize('update', $area);
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            Community::query()->whereKey($area->community_id)->lockForUpdate()->firstOrFail();
            $area = CompanyServiceArea::query()->forCompany($this->context->companyId())->whereKey($area->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $area);
            if ($area->active_to?->lessThanOrEqualTo(today())) {
                return;
            }
            self::validateClosure($area->active_from->toDateString(), $reason);
            $properties = Property::query()->where('community_id', $area->community_id)
                ->whereHas('providerAssignments', fn ($query) => $query->forCompany($area->company_id)->overlapping(today()->toDateString()))
                ->orderBy('id')->lockForUpdate()->get();
            $assignments = PropertyCompanyAssignment::query()->forCompany($area->company_id)
                ->whereIn('property_id', $properties->modelKeys())->overlapping(today()->toDateString())->lockForUpdate()->get();
            foreach ($assignments as $assignment) {
                self::validateClosure($assignment->assigned_from->toDateString(), $reason);
                $assignment->update(['assigned_to' => today(), 'ended_by' => $actor->id, 'end_reason' => trim($reason)]);
            }
            $area->update(['active_to' => today(), 'status' => ServiceAreaStatus::Inactive,
                'ended_by' => $actor->id, 'end_reason' => trim($reason)]);
        });
    }

    public static function validateClosure(string $start, string $reason): void
    {
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
        if ($start >= today()->toDateString()) {
            throw ValidationException::withMessages(['period' => 'Date-only periods must start before today to be closed. Future periods require separate review.']);
        }
    }
}
