<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class PropertyPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsCompany($user, 'properties.view');
    }

    public function view(User $user, Property $record): bool
    {
        return $this->viewAny($user) && Property::query()->visibleToCompany($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->context->allowsCompany($user, 'properties.create');
    }

    public function update(User $user, Property $record): bool
    {
        return $this->context->allowsCompany($user, 'properties.update')
            && Property::query()->currentlyServedBy($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function occupy(User $user, Property $record): bool
    {
        return $this->context->allowsCompany($user, 'residents.create')
            && Property::query()->currentlyServedBy($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function manageOccupants(User $user, Property $record): bool
    {
        return $this->context->allowsCompany($user, 'residents.update')
            && Property::query()->currentlyServedBy($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function delete(User $user, Property $record): bool
    {
        return false;
    }
}
