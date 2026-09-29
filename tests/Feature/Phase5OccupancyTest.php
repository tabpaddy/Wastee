<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyOccupancy;
use App\Models\Resident;
use App\Services\Operations\ResidentOccupancyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\Phase5TestCase;

class Phase5OccupancyTest extends Phase5TestCase
{
    public function test_multiple_occupants_share_property_with_one_contact_and_no_login_accounts(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $first = $this->resident($actor, $company, $property, ['user_id' => $actor->id, 'status' => 'inactive', 'created_by' => 0]);
        $second = $this->resident($actor, $company, $property);
        $this->assertTrue($first->currentOccupancy->is_billing_contact);
        $this->assertFalse($second->currentOccupancy->is_billing_contact);
        $this->assertSame(2, $property->occupancies()->current()->count());
        $this->assertNull($first->user_id);
        $this->assertSame('active', $first->status->value);
        $this->assertSame('7', $first->uuid[14]);
        $this->assertSame($actor->id, $first->currentOccupancy->created_by);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_failed_occupancy_rolls_back_resident_creation(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        try {
            $this->resident($actor, $company, $property, ['occupancy_type' => 'invalid']);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('residents', 0);
            $this->assertDatabaseCount('property_occupancies', 0);
        }
    }

    public function test_foreign_property_cannot_receive_a_new_resident(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        [$other, $foreign] = $this->companyUser();
        $this->expectException(AuthorizationException::class);
        $this->resident($other, $foreign, $property);
    }

    public function test_contact_match_requires_confirmation_and_never_silently_merges_people(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $first = $this->resident($actor, $company, $property, ['phone' => '+234 123-456-7890']);
        try {
            $this->resident($actor, $company, $property, ['phone' => '+2341234567890']);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('residents', 1);
        }
        $second = $this->resident($actor, $company, $property, ['phone' => '+2341234567890', 'confirm_distinct' => true]);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_move_preserves_identity_history_and_adjacent_dates(): void
    {
        [$actor, $company, $community] = $this->covered();
        $source = $this->property($actor, $company, $community);
        $destination = $this->property($actor, $company, $community);
        $resident = $this->resident($actor, $company, $source);
        $old = $resident->currentOccupancy;
        $this->travel(1)->days();
        $new = $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->moveResident($actor, $old, $destination->uuid, 'Moved to another household.'));
        $this->assertSame($resident->id, $new->resident_id);
        $this->assertSame($old->fresh()->move_out_date->toDateString(), $new->move_in_date->toDateString());
        $this->assertTrue($old->fresh()->is_billing_contact);
        $this->assertTrue($new->is_billing_contact);
        $this->assertSame('active', $resident->fresh()->status->value);
        $this->assertSame(2, $resident->occupancies()->count());
        $this->assertSame($actor->id, $old->fresh()->ended_by);
        $this->assertSame($new->id, $resident->fresh()->currentOccupancy->id);
    }

    public function test_departing_contact_requires_a_current_same_property_replacement(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $contact = $this->resident($actor, $company, $property)->currentOccupancy;
        $other = $this->resident($actor, $company, $property)->currentOccupancy;
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, function () use ($actor, $contact, $other): void {
            try {
                app(ResidentOccupancyService::class)->endOccupancy($actor, $contact, 'Resident has departed.');
                $this->fail();
            } catch (ValidationException) {
                $this->assertNull($contact->fresh()->move_out_date);
            }
            app(ResidentOccupancyService::class)->endOccupancy($actor, $contact, 'Resident has departed.', $other->uuid);
            app(ResidentOccupancyService::class)->endOccupancy($actor, $contact, 'Repeated departure request.', $other->uuid);
            $this->assertTrue($other->fresh()->is_billing_contact);
            $this->assertTrue($contact->fresh()->is_billing_contact);
            $this->assertSame(1, $contact->property->occupancies()->current()->billingContact()->count());
        });
    }

    public function test_non_billing_occupant_can_leave_without_changing_contact(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $contact = $this->resident($actor, $company, $property)->currentOccupancy;
        $other = $this->resident($actor, $company, $property)->currentOccupancy;
        $this->travel(1)->days();
        $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->endOccupancy($actor, $other, 'Occupant moved out.'));
        $this->assertTrue($contact->fresh()->is_billing_contact);
        $this->assertNotNull($other->fresh()->move_out_date);
    }

    public function test_billing_contact_change_is_atomic_and_idempotent(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $first = $this->resident($actor, $company, $property)->currentOccupancy;
        $second = $this->resident($actor, $company, $property)->currentOccupancy;
        $this->context()->runForCompany($actor, $company, function () use ($actor, $property, $second): void {
            app(ResidentOccupancyService::class)->changeBillingContact($actor, $property, $second->uuid);
            app(ResidentOccupancyService::class)->changeBillingContact($actor, $property, $second->uuid);
        });
        $this->assertFalse($first->fresh()->is_billing_contact);
        $this->assertTrue($second->fresh()->is_billing_contact);
        $this->assertSame(2, $property->occupancies()->count());
    }

    public function test_database_rejects_two_open_contacts_even_for_direct_writes(): void
    {
        $property = Property::factory()->create();
        PropertyOccupancy::factory()->create(['property_id' => $property->id, 'is_billing_contact' => true]);
        $this->expectException(QueryException::class);
        PropertyOccupancy::factory()->create(['property_id' => $property->id, 'is_billing_contact' => true]);
    }

    public function test_future_occupancy_blocks_current_link_and_same_property_link_is_idempotent(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $resident = $this->resident($actor, $company, $property);
        $this->context()->runForCompany($actor, $company, function () use ($actor, $property, $resident): void {
            $again = app(ResidentOccupancyService::class)->addOccupant($actor, $property, $resident->uuid);
            $same = app(ResidentOccupancyService::class)->moveResident($actor, $again, $property->uuid, 'No address change.');
            $this->assertSame($again->id, $same->id);
            $future = Resident::factory()->create();
            PropertyOccupancy::factory()->create(['resident_id' => $future->id, 'move_in_date' => today()->addDays(2), 'move_out_date' => today()->addDays(4)]);
            $this->expectException(ValidationException::class);
            app(ResidentOccupancyService::class)->addOccupant($actor, $property, $future->uuid);
        });
    }

    public function test_cross_provider_move_requires_departure_and_receiving_company_links_same_identity(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$receiver, $receivingCompany, $otherCommunity] = $this->covered();
        $source = $this->property($actor, $company, $community);
        $destination = $this->property($receiver, $receivingCompany, $otherCommunity);
        $resident = $this->resident($actor, $company, $source);
        $old = $resident->currentOccupancy;
        $this->travel(1)->days();
        try {
            $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->moveResident($actor, $old, $destination->uuid, 'Moving to another provider.'));
            $this->fail();
        } catch (ModelNotFoundException) {
            $this->assertNull($old->fresh()->move_out_date);
        }
        $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->endOccupancy($actor, $old, 'Moving to another provider.'));
        $new = $this->context()->runForCompany($receiver, $receivingCompany, fn () => app(ResidentOccupancyService::class)->addOccupant($receiver, $destination, $resident->uuid));
        $this->assertSame($resident->id, $new->resident_id);
        $this->assertDatabaseCount('residents', 1);
        $this->assertSame(2, $resident->occupancies()->count());
    }

    public function test_unassigned_destination_is_rejected_and_move_rolls_back(): void
    {
        [$actor, $company, $community] = $this->covered();
        $source = $this->property($actor, $company, $community);
        $destination = Property::factory()->create(['community_id' => $community->id]);
        $resident = $this->resident($actor, $company, $source);
        $this->travel(1)->days();
        try {
            $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->moveResident($actor, $resident->currentOccupancy, $destination->uuid, 'Move to unassigned property.'));
            $this->fail();
        } catch (ModelNotFoundException) {
            $this->assertNull($resident->currentOccupancy->fresh()->move_out_date);
        }
    }

    public function test_same_day_departure_is_rejected_without_falsifying_history(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $occupancy = $this->resident($actor, $company, $property)->currentOccupancy;
        try {
            $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->endOccupancy($actor, $occupancy, 'Same day departure request.'));
            $this->fail();
        } catch (ValidationException) {
            $this->assertNull($occupancy->fresh()->move_out_date);
            $this->assertTrue($occupancy->fresh()->is_billing_contact);
        }
    }

    public function test_future_contact_conflict_rolls_back_new_resident_and_occupancy(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        PropertyOccupancy::factory()->create(['property_id' => $property->id, 'is_billing_contact' => true,
            'move_in_date' => today()->addDays(4), 'move_out_date' => today()->addDays(8)]);
        try {
            $this->resident($actor, $company, $property);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('residents', 1);
            $this->assertDatabaseCount('property_occupancies', 1);
            $this->assertSame(0, $property->occupancies()->current()->count());
        }
    }

    public function test_finite_current_contact_can_be_replaced_without_duplicate_responsibility(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $first = $this->resident($actor, $company, $property)->currentOccupancy;
        $first->update(['move_out_date' => today()->addDays(5)]);
        $second = $this->resident($actor, $company, $property)->currentOccupancy;
        $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->changeBillingContact($actor, $property, $second->uuid));
        $this->assertFalse($first->fresh()->is_billing_contact);
        $this->assertTrue($second->fresh()->is_billing_contact);
        $this->assertSame(1, $property->occupancies()->current()->billingContact()->count());
    }

    public function test_foreign_billing_contact_uuid_cannot_clear_existing_contact(): void
    {
        [$actor, $company, $community] = $this->covered();
        [$other, $foreign, $otherCommunity] = $this->covered();
        $property = $this->property($actor, $company, $community);
        $foreignProperty = $this->property($other, $foreign, $otherCommunity);
        $contact = $this->resident($actor, $company, $property)->currentOccupancy;
        $foreignContact = $this->resident($other, $foreign, $foreignProperty)->currentOccupancy;
        try {
            $this->context()->runForCompany($actor, $company, fn () => app(ResidentOccupancyService::class)->changeBillingContact($actor, $property, $foreignContact->uuid));
            $this->fail();
        } catch (ModelNotFoundException) {
            $this->assertTrue($contact->fresh()->is_billing_contact);
            $this->assertTrue($foreignContact->fresh()->is_billing_contact);
        }
    }

    public function test_phone_requires_digits_after_normalization(): void
    {
        [$actor, $company, $community] = $this->covered();
        $property = $this->property($actor, $company, $community);
        try {
            $this->resident($actor, $company, $property, ['phone' => '--------']);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('residents', 0);
        }
    }
}
