<?php

namespace App\Services\Staff;

use App\Enums\CompanyMembershipStatus;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyMembershipPeriod;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Services\Auth\CompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanyMembershipService
{
    public function __construct(private AuthorizationContext $context, private CompanyRoleService $roles) {}

    public function updateProfile(User $actor, CompanyMembership $membership, array $attributes): void
    {
        DB::transaction(function () use ($actor, $membership, $attributes): void {
            $currentMembership = $this->lockAuthorizedMembership($actor, $membership, 'update');
            $profile = Validator::make($attributes, ['first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:40']])->validate();
            $currentMembership->user()->firstOrFail()->update([...$profile,
                'name' => $profile['first_name'].' '.$profile['last_name']]);
        });
    }

    public function deactivate(User $actor, CompanyMembership $membership, string $reason): void
    {
        DB::transaction(function () use ($actor, $membership, $reason): void {
            $currentMembership = $this->lockAuthorizedMembership($actor, $membership, 'deactivate');
            if ($currentMembership->status === CompanyMembershipStatus::Inactive) {
                return;
            }
            $this->validateReason($reason);
            $this->closePeriod($currentMembership, $actor, $reason);
            $currentMembership->update(['status' => CompanyMembershipStatus::Inactive, 'left_at' => now()->startOfSecond()]);
        });
    }

    public function suspend(User $actor, CompanyMembership $membership): void
    {
        DB::transaction(function () use ($actor, $membership): void {
            $currentMembership = $this->lockAuthorizedMembership($actor, $membership, 'suspend');
            if ($currentMembership->status === CompanyMembershipStatus::Suspended) {
                return;
            }
            if ($currentMembership->status !== CompanyMembershipStatus::Active) {
                throw ValidationException::withMessages(['membership' => 'Only active staff can be suspended.']);
            }
            $this->ensureLegacyPeriod($currentMembership);
            $currentMembership->update(['status' => CompanyMembershipStatus::Suspended]);
        });
    }

    public function reactivate(User $actor, CompanyMembership $membership, array $roleUuids = []): void
    {
        DB::transaction(function () use ($actor, $membership, $roleUuids): void {
            $currentMembership = $this->lockAuthorizedMembership($actor, $membership, 'reactivate');
            if ($currentMembership->status === CompanyMembershipStatus::Active) {
                return;
            }
            abort_unless($currentMembership->user->hasVerifiedEmail()
                && app(CompanyAccess::class)->isActiveUser($currentMembership->user), 403);
            if ($currentMembership->status === CompanyMembershipStatus::Suspended) {
                $currentMembership->update(['status' => CompanyMembershipStatus::Active]);

                return;
            }
            $this->startPeriod($currentMembership, $actor, 'Rejoined after deactivation');
            $this->roles->assignRoles($actor, $currentMembership, $roleUuids);
            $currentMembership->periods()->whereNull('left_at')->update(['joined_roles' => $this->roleNames($currentMembership)]);
        });
    }

    public function joinFromInvitation(User $inviter, User $invitedUser, string $roleUuid): CompanyMembership
    {
        return DB::transaction(function () use ($inviter, $invitedUser, $roleUuid): CompanyMembership {
            Gate::forUser($inviter)->authorize('staff.invite');
            $company = Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $invitedUser = User::query()->whereKey($invitedUser->id)->firstOrFail();
            abort_unless($invitedUser->hasVerifiedEmail()
                && app(CompanyAccess::class)->isActiveUser($invitedUser), 403);
            abort_if($company->owner_user_id === $invitedUser->id, 403);
            $companyRole = $this->roles->resolveRoles([$roleUuid])->sole();
            Gate::forUser($inviter)->authorize('invite', $companyRole);
            $membership = $company->memberships()->where('user_id', $invitedUser->id)->lockForUpdate()->first();
            if ($membership && $membership->status !== CompanyMembershipStatus::Inactive) {
                throw ValidationException::withMessages(['invitation' => 'This person already has a membership. Manage that membership instead.']);
            }
            $membership ??= $company->memberships()->create(['user_id' => $invitedUser->id,
                'joined_at' => now()->startOfSecond(), 'status' => CompanyMembershipStatus::Inactive]);
            $this->startPeriod($membership, $inviter, 'Accepted staff invitation');
            $this->roles->assignRoles($inviter, $membership, [$roleUuid]);
            $membership->periods()->whereNull('left_at')->update(['joined_roles' => $this->roleNames($membership)]);

            return $membership;
        });
    }

    private function lockAuthorizedMembership(User $actor, CompanyMembership $membership, string $ability): CompanyMembership
    {
        Gate::forUser($actor)->authorize($ability, $membership);
        $company = Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
        $currentMembership = $company->memberships()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize($ability, $currentMembership);

        return $currentMembership;
    }

    private function startPeriod(CompanyMembership $membership, User $actor, string $reason): void
    {
        $this->ensureLegacyPeriod($membership);
        if ($membership->periods()->whereNull('left_at')->exists()) {
            $this->closePeriod($membership, $actor, 'Closed legacy inactive episode on explicit rejoin; original departure was unknown.');
        }
        $joinedAt = now()->startOfSecond();
        if ($membership->periods()->where(fn ($query) => $query->whereNull('left_at')->orWhere('left_at', '>', $joinedAt))->exists()) {
            throw ValidationException::withMessages(['membership' => 'Membership periods cannot overlap.']);
        }
        $membership->update(['status' => CompanyMembershipStatus::Active, 'joined_at' => $joinedAt, 'left_at' => null]);
        $membership->periods()->create(['joined_at' => $joinedAt, 'joined_by' => $actor->id, 'join_reason' => $reason]);
    }

    private function closePeriod(CompanyMembership $membership, User $actor, string $reason): void
    {
        $period = $this->ensureLegacyPeriod($membership);
        $period = $membership->periods()->where('joined_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('left_at')->orWhere('left_at', '>', now()))->first() ?? $period;
        if (! $period || ($period->left_at !== null && $period->left_at->lessThanOrEqualTo(now()))) {
            return;
        }
        $leftAt = now()->startOfSecond();
        if ($leftAt->lessThanOrEqualTo($period->joined_at)) {
            throw ValidationException::withMessages(['membership' => 'A membership must last at least one second before it can be closed.']);
        }
        $period->update(['left_at' => $leftAt, 'left_by' => $actor->id,
            'leave_reason' => trim($reason), 'left_roles' => $this->roleNames($membership)]);
    }

    private function ensureLegacyPeriod(CompanyMembership $membership): ?CompanyMembershipPeriod
    {
        if ($membership->periods()->exists()) {
            return $membership->periods()->latest('joined_at')->first();
        }
        // New invitation relationships have no completed episode to backfill.
        if ($membership->wasRecentlyCreated) {
            return null;
        }

        return $membership->periods()->create(['joined_at' => $membership->joined_at,
            'left_at' => $membership->left_at, 'join_reason' => 'Preserved legacy membership dates.']);
    }

    private function roleNames(CompanyMembership $membership): array
    {
        return $membership->user->roles()->where('roles.company_id', $membership->company_id)->pluck('name')->all();
    }

    private function validateReason(string $reason): void
    {
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
    }
}
