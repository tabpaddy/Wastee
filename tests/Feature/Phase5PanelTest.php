<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Lga;
use App\Models\Property;
use App\Models\Resident;
use App\Services\Operations\CommunityService;
use App\Services\Operations\PropertyProviderService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Phase5TestCase;

class Phase5PanelTest extends Phase5TestCase
{
    public function test_lists_and_detail_routes_use_legitimate_relationships_and_public_uuids(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$other, $foreign, $otherCommunity] = $this->covered();
        $property = $this->property($actor, $company, $community, ['street' => 'Visible Fixture Street']);
        $hidden = $this->property($other, $foreign, $otherCommunity, ['street' => 'Hidden Fixture Street']);
        $resident = $this->resident($actor, $company, $property, ['email' => 'visible@example.test']);
        $secret = $this->resident($other, $foreign, $hidden, ['email' => 'hidden@example.test']);
        $base = '/company/'.$company->uuid;
        $this->actingAs($actor)->get($base.'/service-areas')->assertOk()->assertSee($community->name)->assertDontSee($otherCommunity->name);
        $this->get($base.'/properties')->assertOk()->assertSee('Visible Fixture Street')->assertDontSee('Hidden Fixture Street')->assertSee('/properties/'.$property->uuid, false);
        $this->get($base.'/properties/'.$property->uuid)->assertOk()->assertSee($property->property_code);
        $this->get($base.'/properties/'.$hidden->uuid)->assertNotFound();
        $this->get($base.'/properties/'.$property->id)->assertNotFound();
        $this->get($base.'/residents')->assertOk()->assertSee('visible@example.test')->assertDontSee('hidden@example.test');
        $this->get($base.'/residents/'.$resident->uuid)->assertOk();
        $this->get($base.'/residents/'.$secret->uuid)->assertNotFound();
        $this->get($base.'/residents/'.$resident->id)->assertNotFound();
        $this->get($base.'/residents/create')->assertNotFound();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_historical_filter_restores_read_only_property_visibility(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community, ['street' => 'Historical Example Address']);
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, fn () => app(PropertyProviderService::class)->release($actor, $property->providerAssignments()->sole(), 'Provider contract ended.'));
        $base = '/company/'.$company->uuid.'/properties';
        $this->actingAs($actor)->get($base)->assertOk()->assertDontSee('Historical Example Address');
        $this->get($base.'?'.http_build_query(['filters' => ['current' => ['isActive' => false]]]))->assertOk()->assertSee('Historical Example Address');
        $this->get($base.'/'.$property->uuid)->assertOk()->assertDontSee('Register occupant');
    }

    public function test_real_livewire_coverage_and_property_creation_use_services(): void
    {
        [$actor, $company] = $this->companyUser();
        $community = Community::factory()->create();
        $base = '/company/'.$company->uuid;
        $snapshot = $this->snapshot($this->actingAs($actor)->get($base.'/service-areas')->assertOk(), 'ListServiceAreas');
        $mounted = $this->livewireCall($snapshot, 'mountAction', ['addCoverage'])->assertOk();
        $this->livewireCall($mounted->json('components.0.snapshot'), 'callMountedAction', [], [
            'mountedActions.0.data.community_uuid' => $community->uuid,
            'mountedActions.0.data.company_id' => 0,
        ])->assertOk();
        $this->assertSame($company->id, $company->serviceAreas()->sole()->company_id);
        $snapshot = $this->snapshot($this->get($base.'/properties/create')->assertOk(), 'CreateProperty');
        $this->livewireCall($snapshot, 'create', [], ['data.community_uuid' => $community->uuid,
            'data.building_number' => '15', 'data.street' => 'Created through Filament', 'data.property_type' => 'residential'])->assertOk();
        $property = Property::sole();
        $this->assertSame($company->id, $property->currentProviderAssignment->company_id);
        $this->assertSame('Created through Filament', $property->street);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_real_resident_registration_action_creates_initial_occupancy(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $snapshot = $this->snapshot($this->actingAs($actor)->get('/company/'.$company->uuid.'/properties/'.$property->uuid)->assertOk(), 'ViewProperty');
        $mounted = $this->livewireCall($snapshot, 'mountAction', ['registerResident'])->assertOk();
        $this->livewireCall($mounted->json('components.0.snapshot'), 'callMountedAction', [], [
            'mountedActions.0.data.first_name' => 'Ada', 'mountedActions.0.data.last_name' => 'Panel Resident',
            'mountedActions.0.data.phone' => '+2348001234567', 'mountedActions.0.data.occupancy_type' => 'tenant',
        ])->assertOk();
        $resident = Resident::sole();
        $this->assertSame($property->id, $resident->currentOccupancy->property_id);
        $this->assertTrue($resident->currentOccupancy->is_billing_contact);
    }

    public function test_livewire_mutation_rechecks_revoked_membership(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $snapshot = $this->snapshot($this->actingAs($actor)->get('/company/'.$company->uuid.'/properties/'.$property->uuid)->assertOk(), 'ViewProperty');
        $mounted = $this->livewireCall($snapshot, 'mountAction', ['editProperty'])->assertOk();
        $company->memberships()->where('user_id', $actor->id)->update(['status' => 'inactive']);
        $this->livewireCall($mounted->json('components.0.snapshot'), 'callMountedAction', [], [
            'mountedActions.0.data.street' => 'Unauthorized edit', 'mountedActions.0.data.property_type' => 'residential',
        ])->assertForbidden();
        $this->assertNotSame('Unauthorized edit', $property->fresh()->street);
    }

    public function test_reference_geography_is_platform_managed_and_empty_states_render(): void
    {
        [$actor, $company] = $this->companyUser();
        $this->actingAs($actor)->get('/company/'.$company->uuid.'/service-areas')->assertOk()->assertSee('reference geography');
        $this->get('/company/'.$company->uuid.'/properties/create')->assertOk()->assertSee('reference geography');
        $platform = $this->platformUser();
        $this->actingAs($platform)->get('/platform/communities')->assertOk()->assertSee('reference data');
        $lga = Lga::factory()->create();
        $community = $this->context()->runForPlatform($platform, fn () => app(CommunityService::class)->create($platform,
            ['lga_id' => $lga->id, 'name' => 'Configured Fixture Community', 'status' => 'inactive']));
        $this->assertSame('active', $community->status->value);
        $this->assertSame('7', $community->uuid[14]);
        $this->context()->runForCompany($actor, $company, function () use ($actor, $lga): void {
            $this->expectException(AuthorizationException::class);
            app(CommunityService::class)->create($actor, ['lga_id' => $lga->id, 'name' => 'Forbidden']);
        });
    }

    public function test_collector_can_read_properties_and_coverage_but_not_residents_or_create(): void
    {
        [$actor, $company, $community] = $this->covered('Waste Collector');
        $this->actingAs($actor)->get('/company/'.$company->uuid.'/service-areas')->assertOk()->assertDontSee('Add coverage');
        $this->get('/company/'.$company->uuid.'/properties')->assertOk();
        $this->get('/company/'.$company->uuid.'/properties/create')->assertForbidden();
        $this->get('/company/'.$company->uuid.'/residents')->assertForbidden();
    }

    public function test_forged_foreign_coverage_table_action_is_rejected(): void
    {
        [$actor, $company] = $this->covered();
        [, , , $foreignArea] = $this->covered();
        $snapshot = $this->snapshot($this->actingAs($actor)->get('/company/'.$company->uuid.'/service-areas')->assertOk(), 'ListServiceAreas');
        $response = $this->livewireCall($snapshot, 'mountAction', ['closeCoverage', [], ['table' => true, 'recordKey' => (string) $foreignArea->id]]);
        $this->assertContains($response->status(), [200, 403, 404]);
        if ($response->status() === 200) {
            $this->livewireCall($response->json('components.0.snapshot'), 'callMountedAction', [], ['mountedActions.0.data.reason' => 'Forged cross-company closure.']);
        }
        $this->assertNull($foreignArea->fresh()->active_to);
    }

    public function test_platform_reader_cannot_create_shared_communities(): void
    {
        $reader = $this->platformUser('Platform Admin');
        $this->actingAs($reader)->get('/platform/communities')->assertOk()->assertDontSee('Create community');
        $this->context()->runForPlatform($reader, function () use ($reader): void {
            $this->expectException(AuthorizationException::class);
            app(CommunityService::class)->create($reader, ['lga_id' => 1, 'name' => 'Unauthorized community']);
        });
    }

    public function test_real_occupancy_move_action_preserves_history(): void
    {
        [$actor, $company, $community] = $this->covered();
        $source = $this->property($actor, $company, $community);
        $destination = $this->property($actor, $company, $community);
        $resident = $this->resident($actor, $company, $source);
        $old = $resident->currentOccupancy;
        $this->travel(1)->days();
        $snapshot = $this->snapshot($this->actingAs($actor)->get('/company/'.$company->uuid.'/residents/'.$resident->uuid)->assertOk(), 'OccupanciesRelationManager');
        $mounted = $this->livewireCall($snapshot, 'mountAction', ['moveResident', [], ['table' => true, 'recordKey' => (string) $old->id]])->assertOk();
        $this->livewireCall($mounted->json('components.0.snapshot'), 'callMountedAction', [], [
            'mountedActions.0.data.destination_uuid' => $destination->uuid,
            'mountedActions.0.data.reason' => 'Moved through the company panel.',
        ])->assertOk();
        $this->assertNotNull($old->fresh()->move_out_date);
        $this->assertSame($destination->id, $resident->fresh()->currentOccupancy->property_id);
        $this->assertSame(2, $resident->occupancies()->count());
    }
}
