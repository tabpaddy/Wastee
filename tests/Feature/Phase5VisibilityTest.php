<?php

namespace Tests\Feature;

use App\Models\PropertyOccupancy;
use App\Models\Resident;
use App\Services\Operations\PropertyProviderService;
use App\Services\Operations\ResidentOccupancyService;
use Tests\Phase5TestCase;

class Phase5VisibilityTest extends Phase5TestCase
{
    public function test_historical_visibility_requires_actual_interval_overlap(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $known = $this->resident($actor, $company, $property);
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, fn () => app(PropertyProviderService::class)->release($actor, $property->providerAssignments()->sole(), 'Provider no longer services property.'));
        $late = Resident::factory()->create(['first_name' => 'UnrelatedNewResident']);
        PropertyOccupancy::factory()->create(['resident_id' => $late->id, 'property_id' => $property->id, 'move_in_date' => today()]);
        $this->assertTrue(Resident::visibleToCompany($company->id)->whereKey($known->id)->exists());
        $this->assertFalse(Resident::visibleToCompany($company->id)->whereKey($late->id)->exists());
        $this->assertSame(0, Resident::currentlyServedBy($company->id)->count());
        $this->context()->runForCompany($actor, $company, function () use ($actor, $known, $late): void {
            $this->assertTrue($actor->can('view', $known));
            $this->assertFalse($actor->can('update', $known));
            $this->assertFalse($actor->can('view', $late));
        });
    }

    public function test_former_provider_cannot_see_later_property_or_occupancy_details(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$other, $foreign, $otherCommunity] = $this->covered();
        $source = $this->property($actor, $company, $community);
        $destination = $this->property($other, $foreign, $otherCommunity, ['street' => 'Private Foreign Destination']);
        $resident = $this->resident($actor, $company, $source);
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->endOccupancy($actor, $resident->currentOccupancy, 'Resident moved to another provider.'));
        $new = $this->context()->runForCompany($other, $foreign, fn () => app(ResidentOccupancyService::class)->addOccupant($other, $destination, $resident->uuid));
        $this->assertFalse(PropertyOccupancy::visibleToCompany($company->id)->whereKey($new->id)->exists());
        $this->actingAs($actor)->get('/company/'.$company->uuid.'/residents/'.$resident->uuid)->assertOk()
            ->assertDontSee('Private Foreign Destination')->assertDontSee($destination->property_code);
        $this->get('/company/'.$company->uuid.'/properties/'.$destination->uuid)->assertNotFound();
    }

    public function test_a_b_platform_a_contexts_do_not_leak_operations_visibility(): void
    {
        $actor = $this->platformUser();
        [, $first] = $this->companyUser('Owner', $actor);
        [, $second] = $this->companyUser('Owner', $actor);
        foreach ([$first, $second, $first] as $company) {
            $this->context()->runForCompany($actor, $company, function () use ($actor, $company): void {
                $this->assertTrue($actor->can('service-areas.manage'));
                $this->assertTrue($actor->can('properties.create'));
                $this->assertTrue($actor->can('residents.create'));
                $this->assertSame($company->id, getPermissionsTeamId());
                $this->assertFalse($actor->can('platform.geography.manage'));
            });
            $this->assertNull(getPermissionsTeamId());
            $this->context()->runForPlatform($actor, function () use ($actor): void {
                $this->assertTrue($actor->can('platform.geography.manage'));
                $this->assertFalse($actor->can('service-areas.manage'));
                $this->assertFalse($actor->can('residents.view'));
            });
        }
    }
}
