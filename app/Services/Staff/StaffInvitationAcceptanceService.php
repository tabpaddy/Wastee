<?php

namespace App\Services\Staff;

use App\Enums\CompanyStatus;
use App\Enums\InvitationStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Services\Auth\CompanyAccess;
use App\Services\Onboarding\OwnerRegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StaffInvitationAcceptanceService
{
    public function __construct(private AuthorizationContext $context, private CompanyMembershipService $memberships) {}

    public function verifyToken(StaffInvitation $invitation, string $token): string
    {
        $proof = hash('sha256', $token);
        $this->inspect($invitation, $proof);

        return $proof;
    }

    public function inspect(StaffInvitation $invitation, ?string $sessionProof): void
    {
        abort_unless($sessionProof && hash_equals($invitation->token_hash, $sessionProof), 403, 'Open the secure link from your invitation email.');
        abort_unless($invitation->isAcceptable(), 410, 'This invitation has expired or is no longer available.');
        abort_unless($invitation->company->status === CompanyStatus::Approved, 403, 'This company is not currently accepting staff.');
    }

    public static function accountRules(): array
    {
        $rules = OwnerRegistrationService::rules();
        unset($rules['email']);

        return $rules;
    }

    public function accept(StaffInvitation $invitation, string $sessionProof, ?User $authenticatedUser, array $account = []): CompanyMembership
    {
        return DB::transaction(function () use ($invitation, $sessionProof, $authenticatedUser, $account): CompanyMembership {
            // All staff workflows lock company before invitation/membership, including acceptance and revocation.
            Company::query()->whereKey($invitation->company_id)->lockForUpdate()->firstOrFail();
            $invitation = StaffInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $this->inspect($invitation, $sessionProof);
            $invitedUser = $this->resolveAccount($invitation, $authenticatedUser, $account);
            $membership = $this->context->runForCompany($invitation->inviter, $invitation->company, function () use ($invitation, $invitedUser): CompanyMembership {
                abort_unless($invitation->role, 403);
                Gate::forUser($invitation->inviter)->authorize('invite', $invitation->role);

                return $this->memberships->joinFromInvitation($invitation->inviter, $invitedUser, $invitation->role->uuid);
            });
            $invitation->update(['status' => InvitationStatus::Accepted, 'accepted_by' => $invitedUser->id, 'accepted_at' => now()]);

            return $membership;
        });
    }

    private function resolveAccount(StaffInvitation $invitation, ?User $authenticatedUser, array $account): User
    {
        if ($authenticatedUser) {
            $invitedUser = User::query()->whereKey($authenticatedUser->id)->lockForUpdate()->firstOrFail();
            abort_unless(strtolower($invitedUser->email) === strtolower($invitation->email) && app(CompanyAccess::class)->isActiveUser($invitedUser), 403,
                'Sign in using the email address that received this invitation.');
        } else {
            if (User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->exists()) {
                throw ValidationException::withMessages(['account' => 'This email already has an account. Sign in to accept.']);
            }
            $profile = Validator::make($account, self::accountRules())->validate();
            unset($profile['password_confirmation']);
            $invitedUser = User::create([...$profile, 'email' => $invitation->email,
                'name' => $profile['first_name'].' '.$profile['last_name']]);
        }
        // A single-use invitation delivered to this exact mailbox proves email ownership.
        // Existing accounts additionally require authentication; a token never replaces their password.
        if (! $invitedUser->hasVerifiedEmail()) {
            $invitedUser->markEmailAsVerified();
        }

        return $invitedUser;
    }
}
