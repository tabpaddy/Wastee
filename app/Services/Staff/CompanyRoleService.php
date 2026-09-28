<?php

namespace App\Services\Staff;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Support\PermissionCatalogue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyRoleService
{
    public function __construct(private AuthorizationContext $context) {}

    public function create(User $actor, array $attributes): Role
    {
        return DB::transaction(function () use ($actor, $attributes): Role {
            Gate::forUser($actor)->authorize('create', Role::class);
            $company = $this->lockCompany();
            Gate::forUser($actor)->authorize('create', Role::class);
            $validated = $this->validateDefinition($actor, $company, $attributes);
            $companyRole = Role::create(['name' => $validated['name'], 'company_id' => $company->id, 'guard_name' => 'web']);
            $companyRole->syncPermissions($validated['permissions']);
            $this->clearPermissions($actor);

            return $companyRole;
        });
    }

    public function update(User $actor, Role $role, array $attributes): Role
    {
        return DB::transaction(function () use ($actor, $role, $attributes): Role {
            Gate::forUser($actor)->authorize('roles.update');
            $company = $this->lockCompany();
            $companyRole = Role::query()->where('company_id', $company->id)->whereKey($role->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $companyRole);
            abort_if($actor->roles()->whereKey($companyRole->id)->exists(), 403, 'You cannot edit a role currently assigned to yourself.');
            $validated = $this->validateDefinition($actor, $company, $attributes, $companyRole);
            $companyRole->update(['name' => $validated['name']]);
            $companyRole->syncPermissions($validated['permissions']);
            $this->clearPermissions($actor);

            return $companyRole;
        });
    }

    public function assignRoles(User $actor, CompanyMembership $membership, array $roleUuids): void
    {
        DB::transaction(function () use ($actor, $membership, $roleUuids): void {
            Gate::forUser($actor)->authorize('roles.assign');
            $company = $this->lockCompany();
            $currentMembership = $company->memberships()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $currentMembership);
            abort_if($currentMembership->user_id === $actor->id, 403, 'You cannot change your own staff roles.');
            $companyRoles = $this->resolveRoles($roleUuids);
            foreach ($companyRoles as $companyRole) {
                Gate::forUser($actor)->authorize('assign', [$companyRole, $currentMembership]);
            }
            $staffUser = $currentMembership->user;
            $staffUser->unsetRelation('roles')->unsetRelation('permissions');
            foreach ($staffUser->roles()->get() as $existingRole) {
                Gate::forUser($actor)->authorize('assign', [$existingRole, $currentMembership]);
            }
            $staffUser->syncRoles($companyRoles);
            $this->clearPermissions($staffUser);
        });
    }

    public function resolveRoles(array $roleUuids): Collection
    {
        Validator::make(['roles' => $roleUuids], ['roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'uuid', 'distinct']])->validate();
        $companyRoles = Role::query()->where('company_id', $this->context->companyId())
            ->where('guard_name', 'web')->whereIn('uuid', $roleUuids)->with('permissions')->get();
        if ($companyRoles->count() !== count($roleUuids)) {
            throw ValidationException::withMessages(['roles' => 'Choose roles belonging to the current company.']);
        }

        return $companyRoles;
    }

    public function assignableRoles(User $actor, bool $invitation = false): Collection
    {
        if (! Gate::forUser($actor)->allows('roles.assign')) {
            return collect();
        }

        return Role::query()->where('company_id', $this->context->companyId())->where('guard_name', 'web')
            ->where('name', '!=', 'Owner')->with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $role) => $invitation
                ? Gate::forUser($actor)->allows('invite', $role)
                : $role->permissions->every(fn ($permission) => Gate::forUser($actor)->allows($permission->name)));
    }

    public function staffFilterOptions(User $actor): array
    {
        Gate::forUser($actor)->authorize('staff.view');

        return Role::query()->where('company_id', $this->context->companyId())->where('guard_name', 'web')
            ->orderBy('name')->pluck('name', 'uuid')->all();
    }

    public function roleOptions(User $actor, bool $invitation = false): array
    {
        return $this->assignableRoles($actor, $invitation)->pluck('name', 'uuid')->all();
    }

    public function clearPermissions(User $user): void
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function lockCompany(): Company
    {
        return Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
    }

    private function validateDefinition(User $actor, Company $company, array $attributes, ?Role $role = null): array
    {
        $attributes['name'] = preg_replace('/\\s+/', ' ', trim($attributes['name'] ?? ''));
        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'min:3', 'max:100',
                Rule::unique('roles')->where('company_id', $company->id)->where('guard_name', 'web')->ignore($role?->id)],
            'permissions' => ['present', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalogue::company())],
        ])->validate();
        $reserved = array_merge(array_keys(PermissionCatalogue::companyRoles()), array_keys(PermissionCatalogue::platformRoles()));
        if (in_array(strtolower($validated['name']), array_map('strtolower', $reserved), true)) {
            throw ValidationException::withMessages(['name' => 'This name is reserved for a system role.']);
        }
        foreach ($validated['permissions'] as $permission) {
            Gate::forUser($actor)->authorize($permission);
        }

        return $validated;
    }
}
