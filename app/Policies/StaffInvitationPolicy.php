<?php

namespace App\Policies;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;

class StaffInvitationPolicy
{
    public function __construct(private AuthorizationContext $context) {}

    public function viewAny(User $actor): bool
    {
        return $this->context->allowsCompany($actor, 'staff.invite');
    }

    public function view(User $actor, StaffInvitation $invitation): bool
    {
        return $invitation->company_id === $this->context->companyId() && $this->viewAny($actor);
    }

    public function update(User $actor, StaffInvitation $invitation): bool
    {
        return $this->view($actor, $invitation);
    }
}
