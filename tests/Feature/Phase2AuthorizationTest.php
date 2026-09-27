<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\PermissionCatalogue;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Phase2TestCase;

class Phase2AuthorizationTest extends Phase2TestCase
{
    public function test_permissions_switch_a_b_platform_reset_a_without_cached_relation_leakage(): void
    {
        [$user, $first] = $this->companyUser('Admin');
        [, $second] = $this->companyUser('Waste Collector', $user);
        $context = $this->context();
        $context->setCompany($user, $first);
        $user->load('roles', 'permissions');
        $this->assertTrue($user->can('staff.create'));
        $context->setCompany($user, $second);
        $this->assertFalse($user->relationLoaded('roles'));
        $this->assertFalse($user->can('staff.create'));
        $this->assertTrue($user->can('collections.record'));
        $context->setPlatform($user);
        $this->assertFalse($user->can('staff.create'));
        $this->assertFalse($user->can('platform.access'));
        $context->reset($user);
        $this->assertNull(getPermissionsTeamId());
        $this->assertFalse($user->can('company.view'));
        $context->setCompany($user, $first->uuid);
        $this->assertSame($first->id, $context->companyId());
        $this->assertSame($user->id, $context->membership()->user_id);
        $this->assertTrue($user->can('staff.create'));
        $this->assertFalse($user->can('platform.companies.review'));
    }

    public function test_context_restores_nested_operations_and_cleans_up_exceptions(): void
    {
        [$user, $company] = $this->companyUser();
        $this->context()->runForCompany($user, $company, function () use ($user, $company): void {
            try {
                $this->context()->runForPlatform($user, fn () => throw new RuntimeException('Failure'));
            } catch (RuntimeException) {
                $this->assertSame($company->id, getPermissionsTeamId());
                $this->assertTrue($user->can('company.view'));
            }
        });
        $this->assertNull(getPermissionsTeamId());
        $this->assertNull($this->context()->company());
        $this->assertFalse($user->relationLoaded('roles'));
    }

    public function test_failed_context_selection_clears_previous_context(): void
    {
        [$user, $company] = $this->companyUser();
        $foreign = Company::factory()->create(['status' => 'approved']);
        $this->context()->setCompany($user, $company);
        try {
            $this->context()->setCompany($user, $foreign);
            $this->fail('Foreign company accepted');
        } catch (AuthorizationException) {
            $this->assertNull(getPermissionsTeamId());
            $this->assertNull($this->context()->companyId());
        }
    }

    public function test_platform_super_admin_is_limited_to_platform_abilities_and_team_zero(): void
    {
        $user = $this->platformUser();
        [$companyUser, $company] = $this->companyUser();
        $this->context()->setPlatform($user);
        $this->assertTrue($user->can('platform.future-administration'));
        $this->assertFalse($user->can('staff.create'));
        $this->assertFalse(Gate::forUser($companyUser)->allows('platform.access'));
        $this->assertFalse(Gate::forUser($user)->allows('update', $company));
        $this->context()->setCompany($companyUser, $company);
        $this->assertFalse($user->can('platform.access'));
        $this->assertFalse($companyUser->can('platform.companies.review'));
    }

    public function test_namesake_super_admin_role_and_misassigned_platform_permission_do_not_escalate(): void
    {
        [$user, $company] = $this->companyUser();
        $this->context()->setCompany($user, $company);
        $role = Role::create(['company_id' => $company->id, 'name' => PermissionCatalogue::PLATFORM_SUPER_ADMIN, 'guard_name' => 'web']);
        $role->givePermissionTo('platform.access');
        $user->assignRole($role);
        $user->givePermissionTo('platform.companies.review');
        $this->assertFalse($user->can('platform.access'));
        $this->assertFalse($user->can('platform.companies.review'));
        $this->context()->setPlatform($user);
        $this->assertFalse($user->can('platform.access'));
    }

    public function test_platform_admin_gets_only_its_seeded_permissions(): void
    {
        $user = $this->platformUser('Platform Admin');
        $this->context()->setPlatform($user);
        $this->assertTrue($user->can('platform.access'));
        $this->assertTrue($user->can('platform.companies.review'));
        $this->assertFalse($user->can('platform.users.manage'));
        $this->assertFalse($user->can('platform.roles.manage'));
    }

    public function test_membership_and_status_revocation_take_effect_within_an_existing_context(): void
    {
        [$user, $company, $membership] = $this->companyUser();
        $this->context()->setCompany($user, $company);
        $this->assertTrue($user->can('billing.issue'));
        $membership->update(['status' => 'inactive']);
        $this->assertFalse($user->can('billing.issue'));
        $membership->update(['status' => 'active']);
        $company->update(['status' => 'suspended']);
        $this->assertFalse($user->can('billing.issue'));
        $company->update(['status' => 'approved']);
        $user->forceFill(['status' => 'suspended'])->save();
        $this->assertFalse($user->can('billing.issue'));
    }

    public function test_company_and_staff_policies_reject_foreign_records(): void
    {
        [$user, $first, $membership] = $this->companyUser();
        [, $second, $foreignMember] = $this->companyUser();
        $this->context()->setCompany($user, $first);
        $gate = Gate::forUser($user);
        $this->assertTrue($gate->allows('view', $first));
        $this->assertTrue($gate->allows('update', $first));
        $this->assertFalse($gate->allows('view', $second));
        $this->assertFalse($gate->allows('update', $second));
        $this->assertTrue($gate->allows('view', $membership));
        $this->assertFalse($gate->allows('view', $foreignMember));
        $this->assertFalse($gate->allows('update', $foreignMember));
        $this->assertFalse($gate->allows('deactivate', $membership));
    }

    public function test_role_and_permission_policies_prevent_foreign_platform_and_owner_assignment(): void
    {
        [$user, $company] = $this->companyUser();
        [, $foreign] = $this->companyUser();
        $staff = CompanyMembership::create(['company_id' => $company->id, 'user_id' => User::factory()->create()->id, 'joined_at' => now()]);
        $this->context()->setCompany($user, $company);
        $gate = Gate::forUser($user);
        $role = Role::where('company_id', $company->id)->where('name', 'Waste Collector')->sole();
        $foreignRole = Role::where('company_id', $foreign->id)->where('name', 'Admin')->sole();
        $platformRole = Role::where('company_id', 0)->where('name', 'Platform Admin')->sole();
        $ownerRole = Role::where('company_id', $company->id)->where('name', 'Owner')->sole();
        $this->assertTrue($gate->allows('assign', [$role, $staff]));
        $this->assertFalse($gate->allows('view', $foreignRole));
        $this->assertFalse($gate->allows('assign', [$foreignRole, $staff]));
        $this->assertFalse($gate->allows('assign', [$platformRole, $staff]));
        $this->assertFalse($gate->allows('assign', [$ownerRole, $staff]));
        $this->assertFalse($gate->allows('update', $ownerRole));
        $permission = Permission::findByName('platform.access');
        $this->assertFalse($gate->allows('view', $permission));
        $this->assertFalse($gate->allows('assignPermission', [$role, $permission]));
        $this->assertTrue($gate->allows('view', Permission::findByName('collections.record')));
        $this->assertFalse($gate->allows('create', Permission::class));
        $staff->update(['left_at' => now()]);
        $this->assertFalse($gate->allows('assign', [$role, $staff]));
    }

    public function test_role_managers_cannot_grant_permissions_they_do_not_hold(): void
    {
        [$user, $company] = $this->companyUser('Waste Collector');
        $this->context()->setCompany($user, $company);
        $user->givePermissionTo(['roles.assign', 'roles.update']);
        $recipient = CompanyMembership::create(['company_id' => $company->id, 'user_id' => User::factory()->create()->id, 'joined_at' => now()]);
        $admin = Role::where('company_id', $company->id)->where('name', 'Admin')->sole();
        $this->assertFalse(app(RolePolicy::class)->assign($user, $admin, $recipient));
        $collector = Role::where('company_id', $company->id)->where('name', 'Waste Collector')->sole();
        $this->assertFalse(app(RolePolicy::class)->assignPermission($user, $collector, Permission::findByName('billing.issue')));
    }

    public function test_onboarding_primitive_does_not_grant_operations_to_unapproved_owner(): void
    {
        [$user, $company] = $this->companyUser();
        $company->update(['status' => 'pending_review']);
        $this->assertTrue(Gate::forUser($user)->allows('viewOnboarding', $company));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('viewOnboarding', $company));
        $this->assertFalse($user->can('company.access'));
    }

    public function test_seeders_are_idempotent_and_do_not_assign_roles_or_create_fake_companies(): void
    {
        $this->assertSame(0, Company::count());
        [$user, $company] = $this->companyUser();
        $before = Role::count();
        $assignments = DB::table('model_has_roles')->count();
        $this->seed(AuthorizationSeeder::class);
        $this->seed(AuthorizationSeeder::class);
        $this->assertSame($before, Role::count());
        $this->assertSame($assignments, DB::table('model_has_roles')->count());
        $this->assertSame(0, Role::whereNull('company_id')->count());
        $this->assertSame(1, Company::count());
        $this->assertSame(count(PermissionCatalogue::company()) + count(PermissionCatalogue::platform()), Permission::count());
    }

    public function test_permission_changes_invalidate_cache_and_loaded_relations(): void
    {
        [$user, $company] = $this->companyUser('Waste Collector');
        $this->context()->setCompany($user, $company);
        $this->assertFalse($user->can('staff.create'));
        $role = Role::where('company_id', $company->id)->where('name', 'Waste Collector')->sole();
        $role->givePermissionTo('staff.create');
        $this->assertTrue($user->can('staff.create'));
        $role->revokePermissionTo('staff.create');
        $this->assertFalse($user->can('staff.create'));
    }

    public function test_worker_boundary_resets_previous_authorization_context(): void
    {
        [$user, $company] = $this->companyUser();
        $this->context()->setCompany($user, $company);
        Event::dispatch(new JobProcessing('sync', $this->createMock(Job::class)));
        $this->assertNull(getPermissionsTeamId());
        $this->assertNull($this->context()->membership());
    }

    public function test_bootstrap_command_creates_hashed_credentials_and_only_team_zero_assignment(): void
    {
        $this->artisan('wastee:make-platform-admin', ['--email' => 'admin@example.test', '--first-name' => 'Platform', '--last-name' => 'Admin'])
            ->expectsQuestion('Password', 'Secur3-Example-Password!')
            ->expectsQuestion('Confirm password', 'Secur3-Example-Password!')
            ->assertSuccessful();
        $user = User::where('email', 'admin@example.test')->sole();
        $this->assertTrue(Hash::check('Secur3-Example-Password!', $user->password));
        $this->assertNull(getPermissionsTeamId());
        $this->context()->runForPlatform($user, fn () => $this->assertTrue($user->can('platform.access')));
        $this->assertSame(0, $user->companyMemberships()->count());
    }

    public function test_bootstrap_command_refuses_existing_accounts_and_noninteractive_password_defaults(): void
    {
        $user = User::factory()->create();
        $this->artisan('wastee:make-platform-admin', ['--email' => $user->email, '--first-name' => 'Existing', '--last-name' => 'User'])->assertFailed();
        $this->artisan('wastee:make-platform-admin', ['--no-interaction' => true])->assertFailed();
        $this->assertSame(1, User::count());
    }

    public function test_sensitive_user_fields_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $user->fill(['status' => 'suspended', 'uuid' => 'changed', 'email_verified_at' => now(), 'company_id' => 123]);
        $this->assertFalse($user->isDirty());
    }

    public function test_bootstrap_rejects_weak_passwords_without_creating_a_user(): void
    {
        $this->artisan('wastee:make-platform-admin', ['--email' => 'admin@example.test', '--first-name' => 'Platform', '--last-name' => 'Admin'])
            ->expectsQuestion('Password', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_temporary_context_does_not_restore_untrusted_ambient_team_state(): void
    {
        [$user, $company] = $this->companyUser();
        setPermissionsTeamId(987654);
        $this->context()->runForCompany($user, $company, fn () => $this->assertTrue($user->can('company.access')));
        $this->assertNull(getPermissionsTeamId());
        $this->assertFalse($user->can('company.access'));
    }
}
