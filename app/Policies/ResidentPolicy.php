<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class ResidentPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsCompany($user, 'residents.view');
    }

    public function view(User $user, Resident $record): bool
    {
        return $this->viewAny($user) && Resident::query()->visibleToCompany($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->context->allowsCompany($user, 'residents.create');
    }

    public function update(User $user, Resident $record): bool
    {
        return $this->context->allowsCompany($user, 'residents.update')
            && Resident::query()->currentlyServedBy($this->context->companyId())->whereKey($record->id)->exists();
    }

    public function delete(User $user, Resident $record): bool
    {
        return false;
    }
}
