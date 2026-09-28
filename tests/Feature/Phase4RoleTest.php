<?php

namespace Tests\Feature;

use App\Services\Staff\CompanyRoleService;
use App\Support\PermissionCatalogue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Phase4TestCase;

class Phase4RoleTest extends Phase4TestCase
{
    public function test_custom_role_creation_update_and_cache_invalidation(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $role = $this->context()->runForCompany($owner, $company, fn () => app(CompanyRoleService::class)->create($owner, [
            'name' => 'Field Coordinator', 'permissions' => ['company.access', 'company.view'], 'company_id' => 0, 'guard_name' => 'api']));
        $this->assertSame($company->id, (int) $role->company_id);
        $this->assertSame('web', $role->guard_name);
        $this->assertSame('7', $role->uuid[14]);
        $this->context()->runForCompany($owner, $company, fn () => app(CompanyRoleService::class)->assignRoles($owner, $membership, [$role->uuid]));
        $this->context()->runForCompany($membership->user, $company, fn () => $this->assertFalse($membership->user->can('staff.view')));
        $this->context()->runForCompany($owner, $company, fn () => app(CompanyRoleService::class)->update($owner, $role, [
            'name' => 'Field Coordinator', 'permissions' => ['company.access', 'company.view', 'staff.view']]));
        $this->context()->runForCompany($membership->user, $company, fn () => $this->assertTrue($membership->user->can('staff.view')));
        $this->assertNull(getPermissionsTeamId());
    }

    public static function reservedNames(): array
    {
        return [['Owner'], ['owner'], [' Admin '], ['Platform Super Admin'], ['Platform Admin'], ['Accountant'], ['Customer Support'], ['Waste Collector']];
    }

    #[DataProvider('reservedNames')]
    public function test_reserved_role_names_are_rejected(string $name): void
    {
        [$owner, $company] = $this->companyUser();
        $this->expectException(ValidationException::class);
        $this->context()->runForCompany($owner, $company, fn () => app(CompanyRoleService::class)->create($owner, [
            'name' => $name, 'permissions' => ['company.access']]));
    }

    public function test_platform_permissions_and_permissions_actor_lacks_are_rejected(): void
    {
        [$owner, $company] = $this->companyUser();
        $this->context()->runForCompany($owner, $company, function () use ($owner): void {
            try {
                app(CompanyRoleService::class)->create($owner, ['name' => 'Forbidden Role', 'permissions' => ['platform.access']]);
                $this->fail('Platform permission accepted');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('roles', ['name' => 'Forbidden Role']);
            }
        });
        [$collector, $otherCompany] = $this->companyUser('Waste Collector');
        $this->context()->runForCompany($collector, $otherCompany, function () use ($collector): void {
            $collector->givePermissionTo('roles.create');
            $this->expectException(AuthorizationException::class);
            app(CompanyRoleService::class)->create($collector, ['name' => 'Overpowered Role', 'permissions' => ['billing.issue']]);
        });
    }

    public function test_all_default_definitions_are_protected(): void
    {
        [$owner, $company] = $this->companyUser();
        $this->context()->runForCompany($owner, $company, function () use ($owner, $company): void {
            foreach (array_keys(PermissionCatalogue::companyRoles()) as $name) {
                $role = $this->companyRole($company, $name);
                try {
                    app(CompanyRoleService::class)->update($owner, $role, ['name' => 'Changed Role', 'permissions' => []]);
                    $this->fail('System role changed');
                } catch (AuthorizationException) {
                    $this->assertSame($name, $role->fresh()->name);
                }
            }
        });
    }

    public function test_multiple_role_assignment_is_idempotent_and_foreign_role_is_denied(): void
    {
        [$owner, $company] = $this->companyUser();
        [, $foreign] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $this->context()->runForCompany($owner, $company, function () use ($owner, $company, $foreign, $membership): void {
            $roles = [$this->companyRole($company)->uuid, $this->companyRole($company, 'Accountant')->uuid];
            app(CompanyRoleService::class)->assignRoles($owner, $membership, $roles);
            app(CompanyRoleService::class)->assignRoles($owner, $membership, $roles);
            $this->assertSame(2, $membership->user->roles()->count());
            $this->expectException(ValidationException::class);
            app(CompanyRoleService::class)->assignRoles($owner, $membership, [$this->companyRole($foreign)->uuid]);
        });
    }

    public function test_owner_role_and_owner_membership_cannot_be_reassigned(): void
    {
        [$owner, $company, $ownerMembership] = $this->companyUser();
        $membership = $this->staff($owner, $company, role: 'Admin');
        $this->context()->runForCompany($owner, $company, function () use ($owner, $company, $membership): void {
            try {
                app(CompanyRoleService::class)->assignRoles($owner, $membership, [$this->companyRole($company, 'Owner')->uuid]);
                $this->fail('Owner role assigned');
            } catch (AuthorizationException) {
                $this->assertSame(['Admin'], $membership->user->roles()->pluck('name')->all());
            }
        });
        $this->expectException(AuthorizationException::class);
        $this->context()->runForCompany($membership->user, $company, fn () => app(CompanyRoleService::class)->assignRoles(
            $membership->user, $ownerMembership, [$this->companyRole($company)->uuid]));
    }

    public function test_self_role_assignment_is_denied(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company, role: 'Admin');
        $admin = $membership->user;
        $this->context()->runForCompany($admin, $company, function () use ($admin, $company, $membership): void {
            $this->expectException(HttpException::class);
            app(CompanyRoleService::class)->assignRoles($admin, $membership, [$this->companyRole($company)->uuid]);
        });
    }

    public function test_company_a_b_platform_contexts_remain_separate_after_staff_mutations(): void
    {
        $actor = $this->platformUser();
        [, $first] = $this->companyUser('Owner', $actor);
        [, $second] = $this->companyUser('Owner', $actor);
        foreach ([$first, $second, $first] as $company) {
            $this->context()->runForCompany($actor, $company, function () use ($actor, $company): void {
                app(CompanyRoleService::class)->create($actor, ['name' => 'Custom '.Str::random(8), 'permissions' => ['company.access']]);
                $this->assertSame($company->id, getPermissionsTeamId());
                $this->assertFalse($actor->can('platform.access'));
            });
            $this->assertNull(getPermissionsTeamId());
            $this->context()->runForPlatform($actor, function () use ($actor): void {
                $this->assertTrue($actor->can('platform.access'));
                $this->assertFalse($actor->can('staff.invite'));
            });
        }
    }
}
