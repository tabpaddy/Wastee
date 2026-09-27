<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\PermissionCatalogue;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;

class AuthorizationContext
{
    private ?User $actor = null;

    private ?CompanyMembership $membership = null;

    private ?int $teamId = null;

    public function __construct(private CompanyAccess $access) {}

    public function company(): ?Company
    {
        return $this->membership?->company;
    }

    public function membership(): ?CompanyMembership
    {
        return $this->membership;
    }

    public function companyId(): ?int
    {
        return $this->membership?->company_id;
    }

    public function teamId(): ?int
    {
        return $this->teamId;
    }

    public function bindRequestScope(string $scope): void
    {
        // Livewire replays middleware on synthetic requests. Bind the real HTTP request
        // so a bundle cannot switch contexts between its component actions.
        $request = app('request');
        $previous = $request->attributes->get('wastee.authorization_scope');
        abort_if($previous !== null && $previous !== $scope, 403, 'Mixed authorization contexts are not allowed.');
        $request->attributes->set('wastee.authorization_scope', $scope);
    }

    public function setPlatform(User $user): void
    {
        $this->reset($user);

        if (! $this->access->isActiveUser($user)) {
            throw new AuthorizationException;
        }

        $this->actor = $user;
        $this->teamId = 0;
        setPermissionsTeamId(0);
    }

    public function setCompany(User $user, Company|int|string $company): void
    {
        $this->reset($user);
        $membership = $this->access->requireOperationalMembership($user, $company);
        $this->actor = $user;
        $this->membership = $membership;
        $this->teamId = $membership->company_id;
        setPermissionsTeamId($this->teamId);
    }

    public function reset(?User $user = null): void
    {
        $this->clearRelations($this->actor);
        $this->clearRelations($user);
        $this->actor = null;
        $this->membership = null;
        $this->teamId = null;
        setPermissionsTeamId(null);
    }

    public function runForPlatform(User $user, Closure $operation): mixed
    {
        return $this->run(fn () => $this->setPlatform($user), $operation, $user);
    }

    public function runForCompany(User $user, Company|int|string $company, Closure $operation): mixed
    {
        return $this->run(fn () => $this->setCompany($user, $company), $operation, $user);
    }

    private function run(Closure $initialize, Closure $operation, User $user): mixed
    {
        $previousActor = $this->actor;
        $previousMembership = $this->membership;
        $previousTeam = $this->teamId;

        try {
            $initialize();

            return $operation();
        } finally {
            $this->reset($user);
            // Restore only a context established by this service, never arbitrary ambient Spatie state.
            $this->actor = $previousActor;
            $this->membership = $previousMembership;
            $this->teamId = $previousTeam;
            $this->clearRelations($previousActor);
            setPermissionsTeamId($previousTeam);
        }
    }

    public function allowsCompany(User $user, string $permission, ?Company $company = null): bool
    {
        if (! in_array($permission, PermissionCatalogue::company(), true)
            || ! $this->matchesActor($user) || ! $this->membership
            || ($company && $company->getKey() !== $this->companyId())) {
            return false;
        }

        $membership = $this->access->membership($user, $this->companyId());

        if (! $membership || ! $this->access->permitsOperations($membership->company)) {
            return false;
        }

        $this->clearRelations($user);

        return $user->checkPermissionTo($permission, 'web');
    }

    public function allowsPlatform(User $user, string $permission): bool
    {
        if (! str_starts_with($permission, 'platform.') || ! $this->matchesActor($user)
            || $this->teamId !== 0 || ! $this->access->isActiveUser($user)) {
            return false;
        }

        $this->clearRelations($user);

        // Check both the assignment context and the role's own team; a namesake company role cannot bypass.
        if ($user->roles()->where('roles.company_id', 0)
            ->where('name', PermissionCatalogue::PLATFORM_SUPER_ADMIN)->where('guard_name', 'web')->exists()) {
            return true;
        }

        return $user->checkPermissionTo($permission, 'web');
    }

    private function matchesActor(User $user): bool
    {
        return $this->actor?->getKey() === $user->getKey()
            && $this->teamId !== null && getPermissionsTeamId() === $this->teamId;
    }

    private function clearRelations(?User $user): void
    {
        $user?->unsetRelation('roles')->unsetRelation('permissions');
    }
}
