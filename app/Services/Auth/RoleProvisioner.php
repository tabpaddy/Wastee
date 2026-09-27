<?php

namespace App\Services\Auth;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleProvisioner
{
    public function seedPlatform(): void
    {
        $this->provision(0, PermissionCatalogue::platformRoles());
    }

    public function seedCompany(Company $company): void
    {
        // Lock the stable tenant row so concurrent provisioning cannot duplicate roles.
        DB::transaction(function () use ($company): void {
            Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $this->provision($company->getKey(), PermissionCatalogue::companyRoles());
        });
    }

    public function assignInitialOwner(Company $company, User $owner): void
    {
        DB::transaction(function () use ($company, $owner): void {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            abort_unless($company->status === CompanyStatus::Draft
                && $company->owner_user_id === $owner->id
                && app(CompanyAccess::class)->isActiveUser($owner)
                && $company->memberships()->current()->where('user_id', $owner->id)->exists(), 403);
            // Provisioning is not operational authorization. Restore only a trusted service context.
            $previous = app(AuthorizationContext::class)->teamId();
            try {
                setPermissionsTeamId($company->id);
                $owner->unsetRelation('roles')->unsetRelation('permissions');
                $owner->assignRole(Role::query()->where('company_id', $company->id)
                    ->where('name', 'Owner')->where('guard_name', 'web')->sole());
            } finally {
                $owner->unsetRelation('roles')->unsetRelation('permissions');
                setPermissionsTeamId($previous);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        });
    }

    public function seedPermissions(): void
    {
        foreach ([...PermissionCatalogue::platform(), ...PermissionCatalogue::company()] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function provision(int $teamId, array $definitions): void
    {
        $previous = getPermissionsTeamId();

        try {
            setPermissionsTeamId($teamId);
            $this->seedPermissions();

            foreach ($definitions as $name => $permissions) {
                $role = Role::query()->firstOrCreate(['company_id' => $teamId, 'name' => $name, 'guard_name' => 'web']);
                $role->syncPermissions($permissions);
            }
        } finally {
            setPermissionsTeamId($previous);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
