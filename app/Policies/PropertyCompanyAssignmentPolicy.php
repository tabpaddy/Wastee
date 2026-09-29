<?php

namespace App\Policies;

use App\Models\PropertyCompanyAssignment;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class PropertyCompanyAssignmentPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function view(User $user, PropertyCompanyAssignment $record): bool
    {
        return $this->context->allowsCompany($user, 'properties.view') && $record->company_id === $this->context->companyId()
            && $record->assigned_from->lessThanOrEqualTo(today());
    }

    public function update(User $user, PropertyCompanyAssignment $record): bool
    {
        return $this->context->allowsCompany($user, 'properties.update') && $record->company_id === $this->context->companyId();
    }

    public function delete(User $user, PropertyCompanyAssignment $record): bool
    {
        return false;
    }
}
