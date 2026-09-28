<?php

namespace Tests\Feature;

use App\Enums\CompanyMembershipStatus;
use App\Models\User;
use App\Services\Onboarding\CompanyRegistrationService;
use App\Services\Staff\CompanyMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Phase4TestCase;

class Phase4MembershipTest extends Phase4TestCase
{
    public function test_suspension_and_reactivation_preserve_one_open_period(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $this->context()->runForCompany($owner, $company, function () use ($owner, $membership): void {
            app(CompanyMembershipService::class)->suspend($owner, $membership);
            app(CompanyMembershipService::class)->suspend($owner, $membership);
        });
        $this->assertSame(CompanyMembershipStatus::Suspended, $membership->fresh()->status);
        $this->assertSame(1, $membership->periods()->whereNull('left_at')->count());
        $this->actingAs($membership->user)->get('/company/'.$company->uuid)->assertForbidden();
        $this->context()->runForCompany($owner, $company, fn () => app(CompanyMembershipService::class)->reactivate($owner, $membership));
        $this->assertSame(1, $membership->periods()->count());
        $this->get('/company/'.$company->uuid)->assertOk();
    }

    public function test_departure_and_rejoin_preserve_period_and_confirm_roles(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $staff = $membership->user;
        [, $otherCompany] = $this->companyUser('Admin', $staff);
        $periodId = $membership->periods()->sole()->id;
        $this->travel(2)->seconds();
        $this->context()->runForCompany($owner, $company, function () use ($owner, $membership): void {
            app(CompanyMembershipService::class)->deactivate($owner, $membership, 'Employment ended.');
            app(CompanyMembershipService::class)->deactivate($owner, $membership, 'Employment ended.');
        });
        $oldPeriod = $membership->periods()->findOrFail($periodId)->getAttributes();
        $this->assertSame($owner->id, $oldPeriod['left_by']);
        $this->assertSame(CompanyMembershipStatus::Inactive, $membership->fresh()->status);
        $this->actingAs($staff)->get('/company/'.$company->uuid)->assertNotFound();
        $this->get('/company/'.$otherCompany->uuid)->assertOk();
        $this->travel(1)->day();
        $rejoined = $this->staff($owner, $company, $staff, 'Accountant');
        $this->assertSame($membership->id, $rejoined->id);
        $this->assertSame(2, $membership->periods()->count());
        $this->assertSame($oldPeriod, $membership->periods()->findOrFail($periodId)->getAttributes());
        $this->context()->runForCompany($staff, $company, function () use ($staff): void {
            $this->assertSame(['Accountant'], $staff->roles()->pluck('name')->all());
        });
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    public function test_direct_rejoin_requires_roles_and_cannot_duplicate_periods(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $this->travel(2)->seconds();
        $this->context()->runForCompany($owner, $company, function () use ($owner, $membership, $company): void {
            $service = app(CompanyMembershipService::class);
            $service->deactivate($owner, $membership, 'Contract ended.');
            try {
                $service->reactivate($owner, $membership);
                $this->fail('Rejoin without roles succeeded');
            } catch (ValidationException) {
                $this->assertSame(1, $membership->periods()->count());
                $this->assertSame(CompanyMembershipStatus::Inactive, $membership->fresh()->status);
            }
            $service->reactivate($owner, $membership, [$this->companyRole($company)->uuid]);
            $service->reactivate($owner, $membership, [$this->companyRole($company)->uuid]);
            $this->assertSame(2, $membership->periods()->count());
        });
    }

    public static function protectedActions(): array
    {
        return [['deactivate'], ['suspend'], ['reactivate']];
    }

    #[DataProvider('protectedActions')]
    public function test_owner_and_self_lifecycle_actions_are_denied(string $action): void
    {
        [$owner, $company, $ownerMembership] = $this->companyUser();
        $adminMembership = $this->staff($owner, $company, role: 'Admin');
        $admin = $adminMembership->user;
        $this->context()->runForCompany($admin, $company, function () use ($admin, $ownerMembership, $adminMembership, $action): void {
            foreach ([$ownerMembership, $adminMembership] as $target) {
                try {
                    $action === 'deactivate'
                        ? app(CompanyMembershipService::class)->deactivate($admin, $target, 'Unsafe action')
                        : app(CompanyMembershipService::class)->$action($admin, $target);
                    $this->fail('Protected membership changed');
                } catch (AuthorizationException) {
                    $this->assertSame(CompanyMembershipStatus::Active, $target->fresh()->status);
                }
            }
        });
    }

    public function test_cross_company_lifecycle_and_profile_forgery(): void
    {
        [$owner, $company] = $this->companyUser();
        [$foreignOwner, $foreignCompany] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $this->context()->runForCompany($owner, $company, function () use ($owner, $membership): void {
            $email = $membership->user->email;
            app(CompanyMembershipService::class)->updateProfile($owner, $membership, ['first_name' => 'Updated', 'last_name' => 'Staff',
                'phone' => '08000000000', 'email' => 'forged@example.test', 'password' => 'forged', 'status' => 'suspended']);
            $this->assertSame($email, $membership->user->fresh()->email);
            $this->assertSame('active', $membership->user->fresh()->status->value);
        });
        $this->expectException(AuthorizationException::class);
        $this->context()->runForCompany($foreignOwner, $foreignCompany, fn () => app(CompanyMembershipService::class)->suspend($foreignOwner, $membership));
    }

    public function test_database_blocks_second_open_period(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $this->expectException(QueryException::class);
        $membership->periods()->create(['joined_at' => now()->addDay()]);
    }

    public function test_database_blocks_overlapping_closed_periods(): void
    {
        [$owner, $company] = $this->companyUser();
        $membership = $this->staff($owner, $company);
        $this->expectException(QueryException::class);
        $membership->periods()->create(['joined_at' => now()->subDay(), 'left_at' => now()->addDay()]);
    }

    public function test_new_owner_registration_creates_history_without_waiting_for_approval(): void
    {
        $owner = User::factory()->create();
        $company = app(CompanyRegistrationService::class)->create($owner, [
            'name' => 'History Company', 'email' => 'office@example.test', 'phone' => '08012345678']);
        $period = $company->memberships()->sole()->periods()->sole();
        $this->assertSame($owner->id, $period->joined_by);
        $this->assertSame(['Owner'], $period->joined_roles);
        $this->assertNull($period->left_at);
    }
}
