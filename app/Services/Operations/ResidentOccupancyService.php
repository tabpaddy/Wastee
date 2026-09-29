<?php

namespace App\Services\Operations;

use App\Enums\OccupancyType;
use App\Enums\PropertyStatus;
use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyOccupancy;
use App\Models\Resident;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResidentOccupancyService
{
    public function __construct(private AuthorizationContext $context, private PropertyProviderService $providers) {}

    public function addOccupant(User $actor, Property $property, string $residentUuid, string $type = 'tenant'): PropertyOccupancy
    {
        return DB::transaction(function () use ($actor, $property, $residentUuid, $type): PropertyOccupancy {
            Gate::forUser($actor)->authorize('occupy', $property);
            $this->lockCompany();
            $property = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('occupy', $property);
            $this->eligibleDestination($property);
            Validator::make(['resident_uuid' => $residentUuid, 'occupancy_type' => $type],
                ['resident_uuid' => ['required', 'uuid'], 'occupancy_type' => ['required', Rule::enum(OccupancyType::class)]])->validate();
            $resident = Resident::query()->active()->where('uuid', $residentUuid)->lockForUpdate()->first();
            if (! $resident) {
                throw ValidationException::withMessages(['resident_uuid' => 'The resident cannot be linked with these details.']);
            }
            $current = $resident->occupancies()->current()->first();
            if ($current?->property_id === $property->id) {
                return $current;
            }

            return $this->startOccupancy($actor, $resident, $property, $type);
        });
    }

    public function moveResident(User $actor, PropertyOccupancy $occupancy, string $destinationUuid, string $reason, ?string $replacementUuid = null): PropertyOccupancy
    {
        return DB::transaction(function () use ($actor, $occupancy, $destinationUuid, $reason, $replacementUuid): PropertyOccupancy {
            Gate::forUser($actor)->authorize('update', $occupancy);
            $this->lockCompany();
            $destination = Property::query()->currentlyServedBy($this->context->companyId())->where('uuid', $destinationUuid)->firstOrFail();
            // Consistent ordering also covers moves in opposite directions between two properties.
            Property::query()->whereIn('id', [$occupancy->property_id, $destination->id])->orderBy('id')->lockForUpdate()->get();
            $resident = Resident::query()->whereKey($occupancy->resident_id)->lockForUpdate()->firstOrFail();
            $occupancy = PropertyOccupancy::query()->whereKey($occupancy->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $occupancy);
            Gate::forUser($actor)->authorize('manageOccupants', $destination);
            $this->eligibleDestination($destination);
            if ($occupancy->property_id === $destination->id) {
                return $occupancy;
            }
            $this->closeOccupancy($actor, $occupancy, $reason, $replacementUuid);

            return $this->startOccupancy($actor, $resident, $destination, $occupancy->occupancy_type->value);
        });
    }

    public function endOccupancy(User $actor, PropertyOccupancy $occupancy, string $reason, ?string $replacementUuid = null): void
    {
        DB::transaction(function () use ($actor, $occupancy, $reason, $replacementUuid): void {
            Gate::forUser($actor)->authorize('manageOccupants', $occupancy->property);
            $this->lockCompany();
            Property::query()->whereKey($occupancy->property_id)->lockForUpdate()->firstOrFail();
            Resident::query()->whereKey($occupancy->resident_id)->lockForUpdate()->firstOrFail();
            $occupancy = PropertyOccupancy::query()->whereKey($occupancy->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageOccupants', $occupancy->property);
            if ($occupancy->move_out_date?->lessThanOrEqualTo(today())) {
                return;
            }
            Gate::forUser($actor)->authorize('update', $occupancy);
            $this->closeOccupancy($actor, $occupancy, $reason, $replacementUuid);
        });
    }

    public function changeBillingContact(User $actor, Property $property, string $occupancyUuid): void
    {
        DB::transaction(function () use ($actor, $property, $occupancyUuid): void {
            Gate::forUser($actor)->authorize('manageOccupants', $property);
            $this->lockCompany();
            $property = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageOccupants', $property);
            $contact = $property->occupancies()->current()->where('uuid', $occupancyUuid)->lockForUpdate()->firstOrFail();
            if ($contact->is_billing_contact) {
                return;
            }
            $property->occupancies()->current()->billingContact()->update(['is_billing_contact' => false]);
            $this->promoteContact($contact);
        });
    }

    private function closeOccupancy(User $actor, PropertyOccupancy $occupancy, string $reason, ?string $replacementUuid): void
    {
        CompanyServiceAreaService::validateClosure($occupancy->move_in_date->toDateString(), $reason);
        $others = $occupancy->property->occupancies()->current()->whereKeyNot($occupancy->id);
        $replacement = null;
        if ($occupancy->is_billing_contact && $others->exists()) {
            $replacement = $others->where('uuid', $replacementUuid)->first();
            if (! $replacement) {
                throw ValidationException::withMessages(['replacement_uuid' => 'Choose a remaining current occupant as the replacement billing contact.']);
            }
        }
        // The closed row retains its departure-time billing flag; later changes only touch current rows.
        $occupancy->update(['move_out_date' => today(), 'ended_by' => $actor->id, 'end_reason' => trim($reason)]);
        if ($replacement) {
            $this->promoteContact($replacement);
        }
    }

    private function startOccupancy(User $actor, Resident $resident, Property $property, string $type): PropertyOccupancy
    {
        if ($resident->occupancies()->overlapping(today()->toDateString())->exists()) {
            throw ValidationException::withMessages(['resident_uuid' => 'This resident has a conflicting current or future occupancy. The former provider must record departure first.']);
        }
        $firstOccupant = ! $property->occupancies()->current()->exists();
        $occupancy = $property->occupancies()->create(['resident_id' => $resident->id, 'move_in_date' => today(),
            'occupancy_type' => $type, 'created_by' => $actor->id, 'is_billing_contact' => false]);
        if ($firstOccupant) {
            $this->promoteContact($occupancy);
        }

        return $occupancy->refresh();
    }

    private function promoteContact(PropertyOccupancy $occupancy): void
    {
        if ($occupancy->property->occupancies()->billingContact()->whereKeyNot($occupancy->id)
            ->overlapping(today()->toDateString(), $occupancy->move_out_date?->toDateString())->exists()) {
            throw ValidationException::withMessages(['billing_contact' => 'A conflicting current or future billing contact exists.']);
        }
        $occupancy->update(['is_billing_contact' => true]);
    }

    private function eligibleDestination(Property $property): void
    {
        if ($property->status !== PropertyStatus::Active) {
            throw ValidationException::withMessages(['property_uuid' => 'Choose an active property.']);
        }
        $this->providers->coverage($property);
    }

    private function lockCompany(): void
    {
        Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
    }
}
