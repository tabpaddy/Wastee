<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CompanyServiceArea;
use App\Models\Property;
use App\Services\Operations\PropertyProviderService;
use App\Services\Operations\PropertyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Phase5TestCase;

class Phase5PropertyTest extends Phase5TestCase
{
    public function test_creation_derives_provider_dates_identity_and_ignores_forged_fields(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community, ['company_id' => 0, 'status' => 'inactive', 'assigned_from' => '2000-01-01']);
        $assignment = $property->providerAssignments()->sole();
        $this->assertSame('7', $property->uuid[14]);
        $this->assertSame('active', $property->status->value);
        $this->assertSame($company->id, $assignment->company_id);
        $this->assertSame($actor->id, $assignment->created_by);
        $this->assertSame(today()->toDateString(), $assignment->assigned_from->toDateString());
        $this->assertNull($property->getAttribute('company_id'));
    }

    public function test_uncovered_creation_fails_without_an_orphan_property(): void
    {
        [$actor, $company] = $this->companyUser();
        try {
            $this->property($actor, $company, Community::factory()->create());
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('properties', 0);
            $this->assertDatabaseCount('property_company_assignments', 0);
        }
    }

    public function test_exact_address_match_requires_explicit_distinct_confirmation(): void
    {
        [$actor, $company, $community] = $this->covered();
        $first = $this->property($actor, $company, $community, ['building_number' => '10']);
        try {
            $this->property($actor, $company, $community, ['building_number' => '10', 'street' => ' fixture street ']);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('properties', 1);
        }
        $second = $this->property($actor, $company, $community, ['building_number' => '10', 'confirm_distinct' => true]);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_release_then_new_provider_reuses_the_same_property_and_keeps_history(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$nextActor, $nextCompany] = $this->covered(community: $community);
        $property = $this->property($actor, $company, $community);
        $original = $property->providerAssignments()->sole();
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, function () use ($actor, $original): void {
            app(PropertyProviderService::class)->release($actor, $original, 'Provider contract has ended.');
            app(PropertyProviderService::class)->release($actor, $original, 'Repeated release request.');
        });
        $this->context()->runForCompany($nextActor, $nextCompany, function () use ($nextActor, $property): void {
            $current = app(PropertyProviderService::class)->assign($nextActor, $property->uuid);
            $again = app(PropertyProviderService::class)->assign($nextActor, $property->uuid);
            $this->assertSame($current->id, $again->id);
        });
        $this->assertSame($nextCompany->id, $property->fresh()->currentProviderAssignment->company_id);
        $this->assertSame(2, $property->providerAssignments()->count());
        $this->assertSame(1, Property::visibleToCompany($company->id)->count());
        $this->assertSame(0, Property::currentlyServedBy($company->id)->count());
        $this->context()->runForCompany($actor, $company, fn () => $this->assertFalse($actor->can('update', $property)));
    }

    public function test_foreign_provider_cannot_seize_an_assigned_property(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$other, $foreign] = $this->covered(community: $community);
        $property = $this->property($actor, $company, $community);
        $this->expectException(ValidationException::class);
        $this->context()->runForCompany($other, $foreign, fn () => app(PropertyProviderService::class)->assign($other, $property->uuid));
    }

    public function test_future_finite_assignment_is_not_current_but_blocks_open_assignment(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = Property::factory()->create(['community_id' => $community->id]);
        $property->providerAssignments()->create(['company_id' => $company->id, 'assigned_from' => today()->addDays(2), 'assigned_to' => today()->addDays(3)]);
        $this->assertNull($property->currentProviderAssignment);
        $this->expectException(ValidationException::class);
        $this->context()->runForCompany($actor, $company, fn () => app(PropertyProviderService::class)->assign($actor, $property->uuid));
    }

    public function test_assignment_is_bounded_by_finite_coverage(): void
    {
        [$actor, $company, $community, $area] = $this->covered();
        $area->update(['active_to' => today()->addDays(10)]);
        $property = $this->property($actor, $company, $community);
        $this->assertSame($area->active_to->toDateString(), $property->providerAssignments()->sole()->assigned_to->toDateString());
    }

    public function test_company_suspension_and_foreign_profile_edit_are_denied(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        [$foreignActor, $foreign] = $this->companyUser();
        $this->context()->runForCompany($foreignActor, $foreign, fn () => $this->assertFalse($foreignActor->can('update', $property)));
        $this->context()->runForCompany($actor, $company, function () use ($actor, $company, $property): void {
            $company->update(['status' => 'suspended']);
            $this->expectException(AuthorizationException::class);
            app(PropertyService::class)->update($actor, $property, ['street' => 'Changed', 'property_type' => 'residential']);
        });
    }

    public function test_assignment_failure_rolls_back_created_property(): void
    {
        [$actor, $company, $community] = $this->covered();
        $this->mock(PropertyProviderService::class, function ($mock): void {
            $mock->shouldReceive('coverage')->once()->andReturn(new CompanyServiceArea);
            $mock->shouldReceive('assign')->once()->andThrow(new \RuntimeException('Forced assignment failure'));
        });
        try {
            $this->property($actor, $company, $community);
            $this->fail();
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('properties', 0);
        }
    }

    public function test_inactive_coverage_blocks_assignment_even_with_an_open_period(): void
    {
        [$actor, $company, $community, $area] = $this->covered();
        $area->update(['status' => 'inactive']);
        $property = Property::factory()->create(['community_id' => $community->id]);
        $this->expectException(ValidationException::class);
        $this->context()->runForCompany($actor, $company, fn () => app(PropertyProviderService::class)->assign($actor, $property->uuid));
    }

    public function test_foreign_provider_cannot_release_another_companys_assignment(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$other, $foreign] = $this->covered(community: $community);
        $property = $this->property($actor, $company, $community);
        $this->travel(1)->days();
        $this->expectException(AuthorizationException::class);
        $this->context()->runForCompany($other, $foreign, fn () => app(PropertyProviderService::class)->release($other,
            $property->providerAssignments()->sole(), 'Forged release attempt.'));
    }
}
