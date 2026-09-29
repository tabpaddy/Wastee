<?php

namespace App\Services\Operations;

use App\Models\Community;
use App\Models\Company;
use App\Models\CompanyServiceArea;
use App\Models\Property;
use App\Models\PropertyCompanyAssignment;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PropertyProviderService
{
    public function __construct(private AuthorizationContext $context) {}

    public function assign(User $actor, string $propertyUuid): PropertyCompanyAssignment
    {
        return DB::transaction(function () use ($actor, $propertyUuid): PropertyCompanyAssignment {
            Gate::forUser($actor)->authorize('properties.create');
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $property = Property::query()->active()->where('uuid', $propertyUuid)->firstOrFail();
            Community::query()->whereKey($property->community_id)->lockForUpdate()->firstOrFail();
            $property = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('properties.create');
            $coverage = $this->coverage($property);
            $current = $property->providerAssignments()->current()->first();
            if ($current && $current->company_id === $this->context->companyId()) {
                return $current;
            }
            $end = $coverage->active_to?->toDateString();
            if ($property->providerAssignments()->overlapping(today()->toDateString(), $end)->exists()) {
                throw ValidationException::withMessages(['property_uuid' => 'This property is unavailable for assignment. Its current provider must release it first.']);
            }

            return $property->providerAssignments()->create(['company_id' => $this->context->companyId(),
                'assigned_from' => today(), 'assigned_to' => $end, 'created_by' => $actor->id]);
        });
    }

    public function release(User $actor, PropertyCompanyAssignment $assignment, string $reason): void
    {
        DB::transaction(function () use ($actor, $assignment, $reason): void {
            Gate::forUser($actor)->authorize('update', $assignment);
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $property = $assignment->property;
            Community::query()->whereKey($property->community_id)->lockForUpdate()->firstOrFail();
            Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            $assignment = PropertyCompanyAssignment::query()->forCompany($this->context->companyId())
                ->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $assignment);
            if ($assignment->assigned_to?->lessThanOrEqualTo(today())) {
                return;
            }
            CompanyServiceAreaService::validateClosure($assignment->assigned_from->toDateString(), $reason);
            $assignment->update(['assigned_to' => today(), 'ended_by' => $actor->id, 'end_reason' => trim($reason)]);
        });
    }

    public function coverage(Property $property): CompanyServiceArea
    {
        $coverage = CompanyServiceArea::query()->forCompany($this->context->companyId())
            ->where('community_id', $property->community_id)->current()->whereHas('community', fn ($query) => $query->active())->first();
        if (! $coverage) {
            throw ValidationException::withMessages(['community_uuid' => 'The company needs active coverage of this community.']);
        }

        return $coverage;
    }
}
