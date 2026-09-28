<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Services\Staff\CompanyMembershipService;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Phase4TestCase;

class Phase4PanelTest extends Phase4TestCase
{
    public function test_company_resources_scope_records_and_use_public_uuid_links(): void
    {
        [$owner, $company] = $this->companyUser();
        [$foreignOwner, $foreignCompany] = $this->companyUser();
        $staff = $this->staff($owner, $company);
        $foreignStaff = $this->staff($foreignOwner, $foreignCompany);
        $platformOnly = $this->platformUser();
        $this->actingAs($owner)->get('/company/'.$company->uuid.'/staff')->assertOk()
            ->assertSee($staff->user->email)->assertDontSee($foreignStaff->user->email)->assertDontSee($platformOnly->email)
            ->assertSee('/staff/'.$staff->user->uuid, false);
        $this->get('/company/'.$company->uuid.'/staff/'.$staff->user->uuid)->assertOk()->assertSee('Membership');
        $this->get('/company/'.$company->uuid.'/staff/'.$foreignStaff->user->uuid)->assertNotFound();
        $this->get('/company/'.$company->uuid.'/staff/'.$staff->id)->assertNotFound();
        $this->get('/company/'.$company->uuid.'/roles')->assertOk()->assertSee('System default');
        $role = $this->companyRole($company);
        $this->get('/company/'.$company->uuid.'/roles/'.$role->uuid)->assertOk()->assertSee('protected');
        $this->get('/company/'.$company->uuid.'/roles/'.$role->uuid.'/edit')->assertForbidden();
        $this->get('/company/'.$company->uuid.'/roles/'.$this->companyRole($foreignCompany)->uuid)->assertNotFound();
        $this->get('/company/'.$company->uuid.'/roles/'.$role->id)->assertNotFound();
        $this->get('/company/'.$company->uuid.'/invitations')->assertOk()->assertSee($staff->user->email)->assertDontSee($foreignStaff->user->email);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_unauthorized_staff_cannot_access_management_resources(): void
    {
        [$owner, $company] = $this->companyUser();
        $staff = $this->staff($owner, $company);
        foreach (['staff', 'roles', 'invitations'] as $resource) {
            $this->actingAs($staff->user)->get('/company/'.$company->uuid.'/'.$resource)->assertForbidden();
        }
    }

    public function test_real_filament_role_create_and_update_use_services(): void
    {
        [$owner, $company] = $this->companyUser();
        $snapshot = $this->snapshot($this->actingAs($owner)->get('/company/'.$company->uuid.'/roles/create')->assertOk(), 'CreateRole');
        $this->update($snapshot, 'create', [], [
            'data.name' => 'Field Coordinator', 'data.permission_groups.company' => ['company.access', 'company.view'],
        ])->assertOk();
        $role = $this->companyRole($company, 'Field Coordinator');
        $this->assertSame(['company.access', 'company.view'], $role->permissions()->orderBy('name')->pluck('name')->all());
        $snapshot = $this->snapshot($this->get('/company/'.$company->uuid.'/roles/'.$role->uuid.'/edit')->assertOk(), 'EditRole');
        $this->update($snapshot, 'save', [], ['data.name' => 'Field Supervisor'])->assertOk();
        $this->assertSame('Field Supervisor', $role->fresh()->name);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_real_staff_suspension_action_replays_tenant_context(): void
    {
        [$owner, $company] = $this->companyUser();
        $staff = $this->staff($owner, $company);
        $snapshot = $this->snapshot($this->actingAs($owner)->get('/company/'.$company->uuid.'/staff/'.$staff->user->uuid)->assertOk(), 'ViewStaff');
        $mounted = $this->update($snapshot, 'mountAction', ['suspend'])->assertOk();
        $this->update($mounted->json('components.0.snapshot'), 'callMountedAction')->assertOk();
        $this->assertSame('suspended', $staff->fresh()->status->value);
        $this->assertSame(1, $staff->periods()->whereNull('left_at')->count());
        $this->actingAs($staff->user)->get('/company/'.$company->uuid)->assertForbidden();
    }

    public function test_staff_action_rejects_revoked_actor_access(): void
    {
        [$owner, $company] = $this->companyUser();
        $admin = $this->staff($owner, $company, role: 'Admin');
        $staff = $this->staff($owner, $company);
        $snapshot = $this->snapshot($this->actingAs($admin->user)->get('/company/'.$company->uuid.'/staff/'.$staff->user->uuid)->assertOk(), 'ViewStaff');
        $mounted = $this->update($snapshot, 'mountAction', ['suspend'])->assertOk();
        $this->context()->runForCompany($owner, $company, fn () => app(CompanyMembershipService::class)->suspend($owner, $admin));
        $this->update($mounted->json('components.0.snapshot'), 'callMountedAction')->assertForbidden();
        $this->assertSame('active', $staff->fresh()->status->value);
    }

    public function test_staff_filters_apply_in_the_database(): void
    {
        [$owner, $company] = $this->companyUser();
        $active = $this->staff($owner, $company);
        $inactive = $this->staff($owner, $company, role: 'Accountant');
        $suspended = $this->staff($owner, $company, role: 'Customer Support');
        $this->travel(2)->seconds();
        $this->context()->runForCompany($owner, $company, function () use ($owner, $inactive, $suspended): void {
            app(CompanyMembershipService::class)->deactivate($owner, $inactive, 'Contract ended.');
            app(CompanyMembershipService::class)->suspend($owner, $suspended);
        });
        $base = '/company/'.$company->uuid.'/staff?';
        $this->actingAs($owner)->get($base.http_build_query(['filters' => ['status' => ['value' => 'inactive']]]))
            ->assertOk()->assertSee($inactive->user->email)->assertDontSee($active->user->email)->assertDontSee($suspended->user->email);
        $this->get($base.http_build_query(['filters' => ['status' => ['value' => 'suspended']]]))
            ->assertOk()->assertSee($suspended->user->email)->assertDontSee($inactive->user->email);
        $this->get($base.http_build_query(['filters' => ['role' => ['value' => $this->companyRole($company)->uuid]]]))
            ->assertOk()->assertSee($active->user->email)->assertDontSee($inactive->user->email)->assertDontSee($suspended->user->email);
    }

    public function test_real_invitation_action_derives_company_and_inviter(): void
    {
        [$owner, $company] = $this->companyUser();
        $snapshot = $this->snapshot($this->actingAs($owner)->get('/company/'.$company->uuid.'/invitations')->assertOk(), 'ListInvitations');
        $mounted = $this->update($snapshot, 'mountAction', ['invite'])->assertOk();
        $this->update($mounted->json('components.0.snapshot'), 'callMountedAction', [], [
            'mountedActions.0.data.email' => 'invited@example.test',
            'mountedActions.0.data.role_uuid' => $this->companyRole($company)->uuid,
        ])->assertOk();
        $invitation = StaffInvitation::sole();
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertNull(getPermissionsTeamId());
    }

    private function snapshot(TestResponse $response, string $component): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if (str_contains(json_decode($snapshot, true)['memo']['name'], $component)) {
                return $snapshot;
            }
        }
        $this->fail('Missing snapshot: '.$component);
    }

    private function update(string $snapshot, string $method, array $params = [], array $updates = []): TestResponse
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [
            ['snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => $params]]],
        ]], ['X-Livewire' => 'true']);
    }
}
