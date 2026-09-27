<?php

namespace Tests;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use App\Services\Auth\RoleProvisioner;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

abstract class Phase2TestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    protected function context(): AuthorizationContext
    {
        return app(AuthorizationContext::class);
    }

    protected function companyUser(string $role = 'Owner', ?User $user = null): array
    {
        $user ??= User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id, 'status' => 'approved']);
        $membership = CompanyMembership::create(['company_id' => $company->id, 'user_id' => $user->id, 'joined_at' => now()->subDay()]);
        app(RoleProvisioner::class)->seedCompany($company);
        $this->context()->runForCompany($user, $company, fn () => $user->assignRole(
            Role::query()->where('company_id', $company->id)->where('name', $role)->sole()
        ));

        return [$user, $company, $membership];
    }

    protected function platformUser(string $role = 'Platform Super Admin'): User
    {
        $user = User::factory()->create();
        $this->context()->runForPlatform($user, fn () => $user->assignRole(
            Role::query()->where('company_id', 0)->where('name', $role)->sole()
        ));

        return $user;
    }

    protected function tearDown(): void
    {
        $this->context()->reset();
        parent::tearDown();
    }
}
