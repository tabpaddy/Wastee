<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\PropertyOccupancy;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class PropertyOccupancyPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function view(User $user, PropertyOccupancy $record): bool
    {
        return $this->context->allowsCompany($user, 'residents.view')
            && PropertyOccupancy::query()->visibleToCompany($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function update(User $user, PropertyOccupancy $record): bool
    {
        return $this->context->allowsCompany($user, 'residents.update')
            && Property::query()->currentlyServedBy($this->context->companyId())->whereKey($record->property_id)->exists()
            && PropertyOccupancy::query()->current()->whereKey($record->id)->exists();
    }

    public function delete(User $user, PropertyOccupancy $record): bool
    {
        return false;
    }
}
