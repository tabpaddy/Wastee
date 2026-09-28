<?php

namespace App\Services\Staff;

use App\Enums\CompanyMembershipStatus;
use App\Enums\InvitationStatus;
use App\Models\Company;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Auth\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffInvitationService
{
    public function __construct(private AuthorizationContext $context, private CompanyRoleService $roles) {}

    public function invite(User $actor, array $attributes): StaffInvitation
    {
        return DB::transaction(function () use ($actor, $attributes): StaffInvitation {
            Gate::forUser($actor)->authorize('staff.invite');
            $company = Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $attributes['email'] = strtolower(trim($attributes['email'] ?? ''));
            $validated = Validator::make($attributes, [
                'email' => ['required', 'email', 'max:255'], 'role_uuid' => ['required', 'uuid'],
            ])->validate();
            $companyRole = $this->roles->resolveRoles([$validated['role_uuid']])->sole();
            Gate::forUser($actor)->authorize('invite', $companyRole);
            $this->assertAvailableRecipient($company, $validated['email']);
            $company->staffInvitations()->pending()->whereRaw('LOWER(email) = ?', [$validated['email']])
                ->where('expires_at', '<=', now())->update(['status' => InvitationStatus::Expired]);
            if ($company->staffInvitations()->pending()->whereRaw('LOWER(email) = ?', [$validated['email']])->exists()) {
                throw ValidationException::withMessages(['email' => 'A pending invitation already exists. Resend or revoke it first.']);
            }
            $token = Str::random(64);
            $invitation = $company->staffInvitations()->create([
                'email' => $validated['email'], 'role_id' => $companyRole->id, 'role_name' => $companyRole->name,
                'token_hash' => hash('sha256', $token), 'invited_by' => $actor->id,
                'status' => InvitationStatus::Pending, 'expires_at' => now()->addDays(7),
            ]);
            $this->notifyAfterCommit($invitation, $token);

            return $invitation;
        });
    }

    public function resend(User $actor, StaffInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation): void {
            $invitation = $this->lockInvitation($actor, $invitation);
            $this->assertPending($invitation);
            abort_unless($invitation->role, 403);
            Gate::forUser($actor)->authorize('invite', $invitation->role);
            $token = Str::random(64);
            $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            $this->notifyAfterCommit($invitation, $token);
        });
    }

    public function revoke(User $actor, StaffInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation): void {
            $invitation = $this->lockInvitation($actor, $invitation);
            if ($invitation->status === InvitationStatus::Revoked) {
                return;
            }
            $this->assertPending($invitation);
            $invitation->update(['status' => InvitationStatus::Revoked]);
        });
    }

    private function lockInvitation(User $actor, StaffInvitation $invitation): StaffInvitation
    {
        Gate::forUser($actor)->authorize('update', $invitation);
        $company = Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();

        $currentInvitation = $company->staffInvitations()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $currentInvitation);

        return $currentInvitation;
    }

    private function assertPending(StaffInvitation $invitation): void
    {
        if (! $invitation->isAcceptable()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is no longer pending or has expired. Issue a new invitation if needed.']);
        }
    }

    private function assertAvailableRecipient(Company $company, string $email): void
    {
        $hasMembership = $company->memberships()->where('status', '!=', CompanyMembershipStatus::Inactive)
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))->exists();
        if ($hasMembership || strtolower($company->owner->email) === $email) {
            throw ValidationException::withMessages(['email' => 'This person already belongs to the company. Manage their existing membership.']);
        }
    }

    private function notifyAfterCommit(StaffInvitation $invitation, string $token): void
    {
        DB::afterCommit(function () use ($invitation, $token): void {
            try {
                Notification::route('mail', $invitation->email)->notify(new StaffInvitationNotification($invitation, $token));
            } catch (\Throwable) {
                // Exception traces may contain an acceptance URL. Log only the public invitation reference.
                Log::warning('Staff invitation delivery failed; resend is available.', ['invitation' => $invitation->uuid]);
            }
        });
    }
}
