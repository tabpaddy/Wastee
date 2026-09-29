<?php

namespace App\Policies;

use App\Models\CompanyServiceArea;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class CompanyServiceAreaPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsCompany($user, 'service-areas.view');
    }

    public function view(User $user, CompanyServiceArea $record): bool
    {
        return $record->company_id === $this->context->companyId() && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->context->allowsCompany($user, 'service-areas.manage');
    }

    public function update(User $user, CompanyServiceArea $record): bool
    {
        return $record->company_id === $this->context->companyId() && $this->create($user);
    }

    public function delete(User $user, CompanyServiceArea $record): bool
    {
        return false;
    }
}
