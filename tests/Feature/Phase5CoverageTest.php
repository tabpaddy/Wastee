<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Services\Operations\CompanyServiceAreaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Phase5TestCase;

class Phase5CoverageTest extends Phase5TestCase
{
    public function test_coverage_creation_rejects_duplicates_and_reopens_without_rewriting_history(): void
    {
        [$actor, $company] = $this->companyUser();
        $community = Community::factory()->create();
        $this->context()->runForCompany($actor, $company, function () use ($actor, $community, $company): void {
            $area = app(CompanyServiceAreaService::class)->add($actor, $community->uuid);
            $this->assertSame($actor->id, $area->created_by);
            try {
                app(CompanyServiceAreaService::class)->add($actor, $community->uuid);
                $this->fail();
            } catch (ValidationException) {
                $this->assertSame(1, $company->serviceAreas()->count());
            }
            $this->travel(1)->days();
            app(CompanyServiceAreaService::class)->close($actor, $area, 'Coverage ended by agreement.');
            $closed = $area->fresh()->getAttributes();
            app(CompanyServiceAreaService::class)->close($actor, $area, 'Repeated request.');
            $next = app(CompanyServiceAreaService::class)->add($actor, $community->uuid);
            $this->assertSame($closed, $area->fresh()->getAttributes());
            $this->assertSame($area->fresh()->active_to->toDateString(), $next->active_from->toDateString());
            $this->assertSame(2, $company->serviceAreas()->count());
        });
    }

    public function test_closure_atomically_releases_properties_and_preserves_occupancies(): void
    {
        [$actor, $company, $community, $area] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $resident = $this->resident($actor, $company, $property);
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, fn () => app(CompanyServiceAreaService::class)->close($actor, $area, 'Contract ended for this community.'));
        $this->assertSame(today()->toDateString(), $property->providerAssignments()->sole()->assigned_to->toDateString());
        $this->assertSame($actor->id, $area->fresh()->ended_by);
        $this->assertNotNull($resident->currentOccupancy);
        $this->assertNull($property->fresh()->currentProviderAssignment);
    }

    public function test_closure_rolls_back_all_assignments_if_one_started_today(): void
    {
        [$actor, $company, $community, $area] = $this->covered();
        $first = $this->property($actor, $company, $community);
        $this->travel(1)->days();
        $second = $this->property($actor, $company, $community);
        try {
            $this->context()->runForCompany($actor, $company, fn () => app(CompanyServiceAreaService::class)->close($actor, $area, 'End the community contract.'));
            $this->fail();
        } catch (ValidationException) {
            $this->assertNull($area->fresh()->active_to);
            $this->assertNull($first->providerAssignments()->sole()->assigned_to);
            $this->assertNull($second->providerAssignments()->sole()->assigned_to);
        }
    }

    public function test_unauthorized_and_foreign_coverage_changes_are_denied(): void
    {
        [$actor, $company, $community] = $this->covered('Accountant');
        [, , , $foreignArea] = $this->covered();
        $this->context()->runForCompany($actor, $company, function () use ($actor, $community, $foreignArea): void {
            $this->assertFalse($actor->can('update', $foreignArea));
            $this->expectException(AuthorizationException::class);
            app(CompanyServiceAreaService::class)->add($actor, $community->uuid);
        });
    }

    public function test_future_finite_coverage_conflicts_with_a_new_open_period(): void
    {
        [$actor, $company] = $this->companyUser();
        $community = Community::factory()->create();
        $company->serviceAreas()->create(['community_id' => $community->id, 'active_from' => today()->addDays(10), 'active_to' => today()->addDays(20)]);
        $this->expectException(ValidationException::class);
        $this->context()->runForCompany($actor, $company, fn () => app(CompanyServiceAreaService::class)->add($actor, $community->uuid));
    }

    public function test_revoked_membership_is_rechecked_inside_an_established_context(): void
    {
        [$actor, $company, $membership] = $this->companyUser();
        $community = Community::factory()->create();
        $this->context()->runForCompany($actor, $company, function () use ($actor, $membership, $community): void {
            $membership->update(['status' => 'suspended']);
            $this->expectException(AuthorizationException::class);
            app(CompanyServiceAreaService::class)->add($actor, $community->uuid);
        });
    }
}
