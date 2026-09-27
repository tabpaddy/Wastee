<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCompanyContext;
use App\Http\Middleware\SetPlatformContext;
use App\Models\Bill;
use App\Models\Company;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\AuthorizationProbe;
use Tests\Phase2TestCase;

class Phase2PanelTest extends Phase2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::addNamespace('phase2', base_path('tests/Fixtures'));
        Livewire::component('authorization-probe', AuthorizationProbe::class);
        Livewire::addPersistentMiddleware([SetCompanyContext::class, SetPlatformContext::class]);
        // These routes exist only in tests. They exercise the real HTTP and Livewire pipelines.
        Route::middleware(['web', SetCompanyContext::class])->group(function (): void {
            Route::get('/_phase2/company/{tenant}/probe', AuthorizationProbe::class);
            Route::get('/_phase2/company/{tenant}/record/{company}', function (string $tenant, string $company) {
                $target = Company::where('uuid', $company)->firstOrFail();
                Gate::authorize('view', $target);

                return response()->json($target->bills()->pluck('invoice_number'));
            });
            Route::get('/_phase2/missing', fn () => response('Unexpected'));
            Route::get('/_phase2/company/{tenant}/exception', fn () => abort(409));
        });
        Route::get('/_phase2/platform', fn () => response()->json(['team' => getPermissionsTeamId()]))
            ->middleware(['web', SetPlatformContext::class]);
    }

    public function test_guests_are_redirected_to_the_matching_panel_login(): void
    {
        $this->get('/platform')->assertRedirect('/platform/login');
        $this->get('/company')->assertRedirect('/company/login');
        $this->get('/platform/login')->assertOk();
        $this->get('/company/login')->assertOk();
        $this->get('/platform/password-reset/request')->assertOk();
        $this->get('/company/password-reset/request')->assertOk();
    }

    public function test_platform_roles_access_platform_but_have_no_implicit_company_access(): void
    {
        foreach (['Platform Super Admin', 'Platform Admin'] as $role) {
            $user = $this->platformUser($role);
            $this->actingAs($user)->get('/platform')->assertOk();
            $this->get('/company')->assertForbidden();
            $this->assertNull(getPermissionsTeamId());
        }
    }

    public static function companyRoles(): array
    {
        return array_map(fn ($role) => [$role], ['Owner', 'Admin', 'Accountant', 'Customer Support', 'Waste Collector']);
    }

    #[DataProvider('companyRoles')]
    public function test_company_roles_access_only_their_approved_company_panel(string $role): void
    {
        [$user, $company] = $this->companyUser($role);
        $this->actingAs($user)->get('/company')->assertRedirect('/company/'.$company->uuid);
        $this->get('/company/'.$company->uuid)->assertOk()->assertSee('Wastee Company');
        $this->get('/platform')->assertForbidden();
        $this->assertNull(getPermissionsTeamId());
    }

    public static function deniedMemberships(): array
    {
        return [['inactive'], ['suspended'], ['future'], ['left']];
    }

    #[DataProvider('deniedMemberships')]
    public function test_inactive_suspended_future_and_ended_memberships_are_denied(string $reason): void
    {
        [$user, $company, $membership] = $this->companyUser();
        $membership->update(match ($reason) {
            'future' => ['joined_at' => now()->addDay()],
            'left' => ['left_at' => now()],
            default => ['status' => $reason],
        });
        $this->actingAs($user)->get('/company/'.$company->uuid)->assertForbidden();
        $this->get('/_phase2/company/'.$company->uuid.'/probe')->assertForbidden();
        $this->assertNull(getPermissionsTeamId());
    }

    public static function blockedCompanyStatuses(): array
    {
        return [['draft'], ['pending_review'], ['correction_required'], ['rejected'], ['suspended']];
    }

    #[DataProvider('blockedCompanyStatuses')]
    public function test_nonapproved_companies_cannot_enter_operations(string $status): void
    {
        [$user, $company] = $this->companyUser();
        $company->update(['status' => $status]);
        $this->actingAs($user)->get('/company/'.$company->uuid)->assertForbidden();
    }

    public function test_inactive_users_are_denied_even_when_membership_or_platform_role_is_valid(): void
    {
        [$user, $company] = $this->companyUser();
        $user->forceFill(['status' => 'inactive'])->save();
        $this->actingAs($user)->get('/company/'.$company->uuid)->assertForbidden();
        $admin = $this->platformUser();
        $admin->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($admin)->get('/platform')->assertForbidden();
    }

    public function test_company_uuid_and_numeric_id_tampering_cannot_select_foreign_tenant(): void
    {
        [$user, $company] = $this->companyUser();
        [, $foreign] = $this->companyUser();
        $this->actingAs($user)->get('/company/'.$foreign->uuid)->assertNotFound();
        $this->get('/company/'.$foreign->id)->assertNotFound();
        $this->get('/company/00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->withSession(['company_id' => $foreign->id])->get('/company/'.$company->uuid.'?company_id='.$foreign->id)->assertOk();
        $this->get('/_phase2/company/'.$foreign->uuid.'/probe')->assertForbidden();
        $this->get('/_phase2/company/unknown/probe')->assertForbidden();
        $this->get('/_phase2/missing')->assertForbidden();
    }

    public function test_company_policy_protects_tenant_data_on_real_requests(): void
    {
        [$user, $company] = $this->companyUser();
        [, $foreign] = $this->companyUser();
        Bill::factory()->create(['company_id' => $company->id, 'invoice_number' => 'OWN-INVOICE']);
        Bill::factory()->create(['company_id' => $foreign->id, 'invoice_number' => 'SECRET-INVOICE']);
        $this->actingAs($user)->get('/_phase2/company/'.$company->uuid.'/record/'.$company->uuid)
            ->assertOk()->assertSee('OWN-INVOICE')->assertDontSee('SECRET-INVOICE');
        $this->get('/_phase2/company/'.$company->uuid.'/record/'.$foreign->uuid)->assertForbidden();
    }

    public function test_multi_company_switching_does_not_reuse_another_companys_permissions(): void
    {
        [$user, $first] = $this->companyUser('Admin');
        [, $second] = $this->companyUser('Waste Collector', $user);
        $this->actingAs($user)->get('/_phase2/company/'.$first->uuid.'/probe')->assertOk()->assertSee('permission:yes');
        $this->get('/_phase2/company/'.$second->uuid.'/probe')->assertOk()->assertSee('permission:no');
        $this->get('/_phase2/company/'.$first->uuid.'/probe')->assertOk()->assertSee('permission:yes');
        $this->get('/company/'.$second->uuid)->assertOk();
        $this->assertNull(getPermissionsTeamId());
        $this->assertNull(Filament::getTenant());
    }

    public function test_platform_middleware_and_exception_paths_reset_context(): void
    {
        $admin = $this->platformUser();
        $this->actingAs($admin)->get('/_phase2/platform')->assertOk()->assertJson(['team' => 0]);
        $this->assertNull(getPermissionsTeamId());
        [$user, $company] = $this->companyUser();
        $this->actingAs($user)->get('/_phase2/platform')->assertForbidden();
        $this->get('/_phase2/company/'.$company->uuid.'/exception')->assertStatus(409);
        $this->assertNull($this->context()->companyId());
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_company_login_uses_filament_credentials_and_logout_invalidates_session(): void
    {
        [$user] = $this->companyUser();
        $snapshot = $this->snapshot($this->get('/company/login')->assertOk(), 'login');
        $this->update($snapshot, 'authenticate', ['data.email' => $user->email, 'data.password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->post('/company/logout')->assertRedirect('/company/login');
        $this->assertGuest();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_platform_login_rejects_company_users_and_wrong_passwords(): void
    {
        [$user] = $this->companyUser();
        $snapshot = $this->snapshot($this->get('/platform/login')->assertOk(), 'login');
        $response = $this->update($snapshot, 'authenticate', ['data.email' => $user->email, 'data.password' => 'password'])->assertOk();
        $this->assertGuest();
        $this->assertStringContainsString('credentials', strtolower($response->getContent()));
        $admin = $this->platformUser();
        $snapshot = $this->snapshot($this->get('/platform/login')->assertOk(), 'login');
        $this->update($snapshot, 'authenticate', ['data.email' => $admin->email, 'data.password' => 'wrong'])->assertOk();
        $this->assertGuest();
        $snapshot = $this->snapshot($this->get('/platform/login')->assertOk(), 'login');
        $this->update($snapshot, 'authenticate', ['data.email' => $admin->email, 'data.password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_livewire_update_reestablishes_context_and_rechecks_revoked_membership(): void
    {
        [$user, $company, $membership] = $this->companyUser('Admin');
        $snapshot = $this->snapshot($this->actingAs($user)->get('/_phase2/company/'.$company->uuid.'/probe')->assertOk(), 'authorization-probe');
        $response = $this->update($snapshot, 'inspect')->assertOk();
        $this->assertStringContainsString('permission:yes', $response->json('components.0.effects.html'));
        $this->assertNull(getPermissionsTeamId());
        $membership->update(['status' => 'suspended']);
        $this->update($snapshot, 'inspect')->assertForbidden();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_native_filament_livewire_dashboard_rechecks_membership(): void
    {
        [$user, $company, $membership] = $this->companyUser();
        $snapshot = $this->snapshot($this->actingAs($user)->get('/company/'.$company->uuid)->assertOk(), 'dashboard');
        $this->update($snapshot, '$refresh')->assertOk();
        $membership->update(['status' => 'inactive']);
        $this->update($snapshot, '$refresh')->assertForbidden();
    }

    public function test_livewire_bundle_cannot_mix_companies_even_for_a_member_of_both(): void
    {
        [$user, $first] = $this->companyUser('Admin');
        [, $second] = $this->companyUser('Waste Collector', $user);
        $a = $this->snapshot($this->actingAs($user)->get('/_phase2/company/'.$first->uuid.'/probe')->assertOk(), 'authorization-probe');
        $b = $this->snapshot($this->get('/_phase2/company/'.$second->uuid.'/probe')->assertOk(), 'authorization-probe');
        $this->postJson(Livewire::getUpdateUri(), ['components' => [
            ['snapshot' => $a, 'updates' => [], 'calls' => [['method' => 'inspect', 'params' => []]]],
            ['snapshot' => $b, 'updates' => [], 'calls' => [['method' => 'inspect', 'params' => []]]],
        ]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_platform_admin_requires_deliberate_membership_and_company_role_for_company_access(): void
    {
        $user = $this->platformUser();
        $this->actingAs($user)->get('/company')->assertForbidden();
        [, $company] = $this->companyUser('Admin', $user);
        $this->get('/company/'.$company->uuid)->assertOk();
        $this->get('/platform')->assertOk();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_neither_roles_without_membership_nor_membership_without_permission_grant_access(): void
    {
        [$user, $company, $membership] = $this->companyUser();
        $membership->delete();
        $this->actingAs($user)->get('/company/'.$company->uuid)->assertForbidden();
        [$other, $otherCompany] = $this->companyUser();
        $this->context()->runForCompany($other, $otherCompany, fn () => $other->syncRoles([]));
        $this->actingAs($other)->get('/company/'.$otherCompany->uuid)->assertForbidden();
    }

    public function test_context_middleware_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/_phase2/platform')->assertUnauthorized();
        $this->getJson('/_phase2/company/unknown/probe')->assertUnauthorized();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_livewire_rechecks_company_status_and_rejects_another_users_snapshot(): void
    {
        [$user, $company] = $this->companyUser();
        $snapshot = $this->snapshot($this->actingAs($user)->get('/company/'.$company->uuid)->assertOk(), 'dashboard');
        [$other] = $this->companyUser();
        $this->actingAs($other);
        $this->update($snapshot, '$refresh')->assertNotFound();
        $this->actingAs($user);
        $company->update(['status' => 'suspended']);
        $this->update($snapshot, '$refresh')->assertForbidden();
    }

    public function test_platform_livewire_updates_recheck_revoked_platform_role(): void
    {
        $user = $this->platformUser();
        $snapshot = $this->snapshot($this->actingAs($user)->get('/platform')->assertOk(), 'dashboard');
        $this->update($snapshot, '$refresh')->assertOk();
        $this->context()->runForPlatform($user, fn () => $user->syncRoles([]));
        $this->update($snapshot, '$refresh')->assertForbidden();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_livewire_bundle_cannot_mix_platform_and_company_contexts(): void
    {
        $user = $this->platformUser();
        [, $company] = $this->companyUser('Admin', $user);
        $platform = $this->snapshot($this->actingAs($user)->get('/platform')->assertOk(), 'dashboard');
        $companySnapshot = $this->snapshot($this->get('/company/'.$company->uuid)->assertOk(), 'dashboard');
        $this->postJson(Livewire::getUpdateUri(), ['components' => [
            ['snapshot' => $platform, 'updates' => [], 'calls' => [['method' => '$refresh', 'params' => []]]],
            ['snapshot' => $companySnapshot, 'updates' => [], 'calls' => [['method' => '$refresh', 'params' => []]]],
        ]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_future_departure_allows_access_until_membership_ends(): void
    {
        [$user, $company, $membership] = $this->companyUser();
        $membership->update(['left_at' => now()->addDay()]);
        $this->actingAs($user)->get('/company/'.$company->uuid)->assertOk();
        $this->travel(2)->days();
        $this->get('/company/'.$company->uuid)->assertForbidden();
    }

    public function test_users_can_log_out_after_company_or_platform_access_is_revoked(): void
    {
        [$user, $company] = $this->companyUser();
        $company->update(['status' => 'suspended']);
        $this->actingAs($user)->get('/company/'.$company->uuid)->assertForbidden();
        $this->post('/company/logout')->assertRedirect('/company/login');
        $this->assertGuest();
        $admin = $this->platformUser();
        $admin->forceFill(['status' => 'inactive'])->save();
        $this->actingAs($admin)->get('/platform')->assertForbidden();
        $this->post('/platform/logout')->assertRedirect('/platform/login');
        $this->assertGuest();
        $this->assertNull(getPermissionsTeamId());
    }

    private function snapshot(TestResponse $response, string $component): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            $name = strtolower(str_replace(['-', '\\'], '', json_decode($snapshot, true)['memo']['name']));
            if (str_contains($name, str_replace('-', '', $component))) {
                return $snapshot;
            }
        }

        $names = array_map(fn ($encoded) => json_decode(html_entity_decode($encoded, ENT_QUOTES), true)['memo']['name'], $matches[1]);
        $this->fail('Component snapshot not found: '.$component.'; found: '.implode(', ', $names));
    }

    private function update(string $snapshot, string $method, array $updates = []): TestResponse
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [
            ['snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => []]]],
        ]], ['X-Livewire' => 'true']);
    }
}
