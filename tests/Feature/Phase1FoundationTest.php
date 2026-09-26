<?php

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Enums\CompanyStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\Bill;
use App\Models\CollectionRecord;
use App\Models\CollectionSchedule;
use App\Models\CollectionZone;
use App\Models\Community;
use App\Models\Company;
use App\Models\CompanyApprovalLog;
use App\Models\CompanyDocument;
use App\Models\CompanyLocation;
use App\Models\CompanyMembership;
use App\Models\CompanyServiceArea;
use App\Models\CompanySetting;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyCompanyAssignment;
use App\Models\PropertyOccupancy;
use App\Models\Receipt;
use App\Models\Resident;
use App\Models\ServicePlan;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 26)->startOfDay());
    }

    public function test_owner_memberships_settings_and_geography_relationships(): void
    {
        $company = Company::factory()->create();
        $user = $company->owner;
        $membership = CompanyMembership::create(['company_id' => $company->id, 'user_id' => $user->id, 'joined_at' => now()]);
        $settings = CompanySetting::create(['company_id' => $company->id]);
        $community = Community::factory()->create();

        $this->assertTrue($user->ownedCompanies->first()->is($company));
        $this->assertTrue($user->companyMemberships->first()->is($membership));
        $this->assertTrue($membership->user->is($user));
        $this->assertTrue($company->memberships->first()->is($membership));
        $this->assertTrue($company->settings->is($settings));
        $this->assertSame('NGN', $settings->fresh()->currency);
        $this->assertTrue($community->lga->communities->first()->is($community));
        $this->assertTrue($community->lga->state->lgas->first()->is($community->lga));
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(CompanyStatus::Draft, $company->fresh()->status);
        $this->assertTrue(Str::isUuid($company->uuid));
        $this->assertIsInt($company->id);
        $this->assertSame('id', $company->getRouteKeyName());
    }

    public function test_company_locations_preserve_relocation_history_and_current_scope(): void
    {
        $company = Company::factory()->create();
        $community = Community::factory()->create();
        $attributes = ['company_id' => $company->id, 'label' => 'Head Office', 'address_line' => '1 Old Street',
            'lga_id' => $community->lga_id, 'state_id' => $community->lga->state_id, 'is_head_office' => true];
        $old = CompanyLocation::create($attributes + ['active_from' => '2025-01-01', 'active_to' => '2026-09-01', 'status' => 'closed']);
        $new = CompanyLocation::create(array_replace($attributes, ['address_line' => '2 New Street', 'active_from' => '2026-09-01']));
        $this->assertCount(2, $company->locations);
        $this->assertTrue(CompanyLocation::current()->sole()->is($new));
        $this->assertTrue($old->fresh()->company->is($company));
        $this->assertTrue($new->lga->is($community->lga));
    }

    public function test_moving_resident_and_changing_provider_preserves_financial_history(): void
    {
        $resident = Resident::factory()->create();
        $property = Property::factory()->create();
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $old = PropertyOccupancy::factory()->create(['resident_id' => $resident->id, 'property_id' => $property->id, 'move_in_date' => '2026-01-01', 'move_out_date' => '2026-09-01']);
        $current = PropertyOccupancy::factory()->create(['resident_id' => $resident->id, 'move_in_date' => '2026-09-01']);
        $previousProvider = PropertyCompanyAssignment::create(['property_id' => $property->id, 'company_id' => $firstCompany->id, 'assigned_from' => '2025-01-01', 'assigned_to' => '2026-09-01']);
        $newProvider = PropertyCompanyAssignment::create(['property_id' => $property->id, 'company_id' => $secondCompany->id, 'assigned_from' => '2026-09-01']);
        $plan = ServicePlan::factory()->create(['company_id' => $firstCompany->id]);
        $bill = Bill::factory()->create(['company_id' => $firstCompany->id, 'property_occupancy_id' => $old->id, 'service_plan_id' => $plan->id]);
        $payment = Payment::factory()->successful()->create(['bill_id' => $bill->id]);
        $receipt = Receipt::factory()->create(['payment_id' => $payment->id]);
        $snapshot = $bill->company_snapshot;
        $resident->update(['first_name' => 'Changed']);
        $firstCompany->update(['name' => 'Renamed']);
        $plan->update(['amount' => '9999.00']);

        $this->assertNull($resident->user_id);
        $this->assertCount(2, $resident->occupancies);
        $this->assertTrue($resident->currentOccupancy->is($current));
        $this->assertTrue($property->currentProviderAssignment->is($newProvider));
        $this->assertCount(2, $property->providerAssignments);
        $this->assertTrue($previousProvider->fresh()->company->is($firstCompany));
        $this->assertTrue($bill->fresh()->occupancy->is($old));
        $this->assertTrue($bill->company->is($firstCompany));
        $this->assertTrue($old->bills->first()->is($bill));
        $this->assertTrue($bill->servicePlan->is($plan));
        $this->assertSame($snapshot, $bill->fresh()->company_snapshot);
        $this->assertSame('5000.00', $bill->plan_snapshot['rate']);
        $this->assertTrue($payment->bill->is($bill));
        $this->assertTrue($payment->company->is($firstCompany));
        $this->assertTrue($payment->receipt->is($receipt));
        $this->assertTrue($receipt->payment->is($payment));
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame('5000.00', $payment->amount);
        $this->assertSame(BillStatus::Issued, $bill->status);
        $this->assertFalse(Schema::hasColumn('properties', 'resident_id'));
        $this->assertFalse(Schema::hasColumn('properties', 'company_id'));
        $this->assertFalse(Schema::hasColumn('residents', 'company_id'));
    }

    public function test_current_scopes_use_half_open_dates_and_exclude_future_rows(): void
    {
        $resident = Resident::factory()->create();
        $past = PropertyOccupancy::factory()->create(['resident_id' => $resident->id, 'move_in_date' => '2026-01-01', 'move_out_date' => '2026-09-26']);
        $present = PropertyOccupancy::factory()->create(['resident_id' => $resident->id, 'move_in_date' => '2026-09-26', 'move_out_date' => '2026-10-01']);
        PropertyOccupancy::factory()->create(['resident_id' => $resident->id, 'move_in_date' => '2026-10-01']);
        $this->assertTrue(PropertyOccupancy::current()->sole()->is($present));
        $this->assertTrue(PropertyOccupancy::current('2026-09-25')->sole()->is($past));
        $this->assertTrue($resident->currentOccupancy->is($present));

        $company = Company::factory()->create(['status' => CompanyStatus::Approved]);
        Company::factory()->create(['status' => CompanyStatus::PendingReview]);
        $this->assertTrue(Company::approved()->sole()->is($company));
        $this->assertSame(1, Company::pendingReview()->count());
        $bill = Bill::factory()->create(['company_id' => $company->id, 'due_at' => now()->subDay()]);
        Bill::factory()->create(['company_id' => $company->id, 'status' => BillStatus::Void, 'due_at' => now()->subDay()]);
        Bill::factory()->create(['status' => BillStatus::Paid, 'outstanding_amount' => 0, 'amount_paid' => 5000]);
        $this->assertTrue(Bill::overdue()->sole()->is($bill));
        $this->assertSame(1, Bill::unpaid()->count());
        $this->assertSame(2, Bill::forCompany($company)->count());
        $successful = Payment::factory()->successful()->create(['bill_id' => $bill->id]);
        Payment::factory()->create(['bill_id' => $bill->id]);
        $this->assertTrue(Payment::successful()->sole()->is($successful));
    }

    public function test_coverage_documents_invitations_and_approval_relationships(): void
    {
        $company = Company::factory()->create();
        $community = Community::factory()->create();
        $coverage = CompanyServiceArea::create(['company_id' => $company->id, 'community_id' => $community->id, 'active_from' => '2026-01-01']);
        $document = CompanyDocument::create(['company_id' => $company->id, 'document_type' => 'registration_certificate', 'original_filename' => 'certificate.pdf', 'path' => 'private/certificate.pdf']);
        $invitation = StaffInvitation::create(['company_id' => $company->id, 'email' => 'staff@example.com', 'token_hash' => hash('sha256', Str::random(64)), 'invited_by' => $company->owner_user_id, 'role_name' => 'Collector', 'expires_at' => now()->addDay()]);
        $log = CompanyApprovalLog::create(['company_id' => $company->id, 'actor_user_id' => $company->owner_user_id, 'action' => 'submitted', 'from_status' => 'draft', 'to_status' => 'pending_review', 'metadata' => ['version' => 1]]);
        $this->assertTrue($company->serviceAreas->first()->is($coverage));
        $this->assertTrue($community->companyServiceAreas->first()->is($coverage));
        $this->assertTrue(CompanyServiceArea::current()->sole()->is($coverage));
        $this->assertTrue($company->documents->first()->is($document));
        $this->assertTrue($company->approvalLogs->first()->is($log));
        $this->assertTrue($invitation->inviter->is($company->owner));
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
        $this->assertSame(['version' => 1], $log->metadata);
    }

    public function test_collection_and_complaint_history_relationships(): void
    {
        $company = Company::factory()->create();
        $occupancy = PropertyOccupancy::factory()->create();
        CompanyMembership::create(['company_id' => $company->id, 'user_id' => $company->owner_user_id, 'joined_at' => now()]);
        $zone = CollectionZone::create(['company_id' => $company->id, 'name' => 'Central']);
        $zone->properties()->attach($occupancy->property_id, ['company_id' => $company->id]);
        $schedule = CollectionSchedule::create(['company_id' => $company->id, 'collection_zone_id' => $zone->id, 'day_of_week' => 1]);
        $record = CollectionRecord::create(['company_id' => $company->id, 'property_id' => $occupancy->property_id, 'property_occupancy_id' => $occupancy->id, 'collection_schedule_id' => $schedule->id, 'collector_user_id' => $company->owner_user_id, 'status' => 'collected', 'attempted_at' => now(), 'collected_at' => now(), 'property_snapshot' => ['address' => 'Original Street']]);
        $complaint = Complaint::create(['company_id' => $company->id, 'resident_id' => $occupancy->resident_id, 'property_occupancy_id' => $occupancy->id, 'category' => 'missed_collection', 'subject' => 'Missed bin', 'description' => 'Please collect', 'assigned_to' => $company->owner_user_id]);
        $message = ComplaintMessage::create(['complaint_id' => $complaint->id, 'sender_resident_id' => $occupancy->resident_id, 'message' => 'Thank you']);
        $zone->properties()->detach();
        $occupancy->update(['move_out_date' => today()]);
        $this->assertTrue($schedule->zone->is($zone));
        $this->assertTrue($record->property->is($occupancy->property));
        $this->assertTrue($record->collector->is($company->owner));
        $this->assertTrue($record->fresh()->occupancy->is($occupancy));
        $this->assertTrue($complaint->company->is($company));
        $this->assertTrue($complaint->resident->is($occupancy->resident));
        $this->assertTrue($complaint->occupancy->is($occupancy));
        $this->assertTrue($complaint->assignee->is($company->owner));
        $this->assertTrue($complaint->messages->first()->is($message));
        $this->assertTrue(Complaint::open()->sole()->is($complaint));
    }

    public static function uniqueCases(): array
    {
        return array_map(fn ($name) => [$name], ['membership', 'occupancy', 'provider', 'coverage', 'payment_reference', 'receipt_payment', 'receipt_number', 'invoice_number']);
    }

    #[DataProvider('uniqueCases')]
    public function test_database_rejects_duplicates(string $invariant): void
    {
        $company = Company::factory()->create();
        $operation = match ($invariant) {
            'membership' => function () use ($company) {
                $attributes = ['company_id' => $company->id, 'user_id' => $company->owner_user_id, 'joined_at' => now()];
                CompanyMembership::create($attributes);

                return fn () => CompanyMembership::create($attributes);
            },
            'occupancy' => function () {
                $row = PropertyOccupancy::factory()->create();

                return fn () => PropertyOccupancy::factory()->create(['resident_id' => $row->resident_id]);
            },
            'provider' => function () use ($company) {
                $attributes = ['company_id' => $company->id, 'property_id' => Property::factory()->create()->id, 'assigned_from' => today()];
                PropertyCompanyAssignment::create($attributes);

                return fn () => PropertyCompanyAssignment::create($attributes);
            },
            'coverage' => function () use ($company) {
                $attributes = ['company_id' => $company->id, 'community_id' => Community::factory()->create()->id, 'active_from' => today()];
                CompanyServiceArea::create($attributes);

                return fn () => CompanyServiceArea::create($attributes);
            },
            'payment_reference' => function () {
                $row = Payment::factory()->create();

                return fn () => Payment::factory()->create(['payment_reference' => $row->payment_reference]);
            },
            'receipt_payment' => function () {
                $row = Receipt::factory()->create();

                return fn () => Receipt::factory()->create(['payment_id' => $row->payment_id]);
            },
            'receipt_number' => function () use ($company) {
                $bill = Bill::factory()->create(['company_id' => $company->id]);
                $row = Receipt::factory()->create(['payment_id' => Payment::factory()->successful()->create(['bill_id' => $bill->id])->id]);
                $payment = Payment::factory()->successful()->create(['bill_id' => $bill->id]);

                return fn () => Receipt::factory()->create(['payment_id' => $payment->id, 'receipt_number' => $row->receipt_number]);
            },
            'invoice_number' => function () use ($company) {
                $row = Bill::factory()->create(['company_id' => $company->id]);

                return fn () => Bill::factory()->create(['company_id' => $company->id, 'invoice_number' => $row->invoice_number]);
            },
        };
        $duplicate = $operation();
        $this->expectException(QueryException::class);
        $duplicate();
    }

    public function test_invoice_and_receipt_numbers_can_repeat_between_companies(): void
    {
        $first = Bill::factory()->create(['invoice_number' => 'INV-001']);
        $second = Bill::factory()->create(['invoice_number' => 'INV-001']);
        Receipt::factory()->create(['payment_id' => Payment::factory()->successful()->create(['bill_id' => $first->id])->id, 'receipt_number' => 'RCT-001']);
        Receipt::factory()->create(['payment_id' => Payment::factory()->successful()->create(['bill_id' => $second->id])->id, 'receipt_number' => 'RCT-001']);
        $this->assertSame(2, Receipt::count());
    }

    public static function foreignKeyCases(): array
    {
        return array_map(fn ($name) => [$name], ['owner', 'occupancy_resident', 'payment_company', 'receipt_company', 'bill_plan_company', 'complaint_resident', 'schedule_company']);
    }

    #[DataProvider('foreignKeyCases')]
    public function test_foreign_keys_reject_invalid_or_cross_company_references(string $invariant): void
    {
        $company = Company::factory()->create();
        $operation = match ($invariant) {
            'owner' => fn () => Company::factory()->create(['owner_user_id' => 999999999]),
            'occupancy_resident' => fn () => PropertyOccupancy::factory()->create(['resident_id' => 999999999]),
            'payment_company' => fn () => Payment::factory()->create(['company_id' => $company->id]),
            'receipt_company' => fn () => Receipt::factory()->create(['company_id' => $company->id]),
            'bill_plan_company' => fn () => Bill::factory()->create(['company_id' => $company->id, 'service_plan_id' => ServicePlan::factory()->create()->id]),
            'complaint_resident' => fn () => Complaint::create(['company_id' => $company->id, 'resident_id' => Resident::factory()->create()->id, 'property_occupancy_id' => PropertyOccupancy::factory()->create()->id, 'category' => 'other', 'subject' => 'Wrong resident', 'description' => 'Invalid']),
            'schedule_company' => fn () => CollectionSchedule::create(['company_id' => $company->id, 'collection_zone_id' => CollectionZone::create(['company_id' => Company::factory()->create()->id, 'name' => 'Other'])->id, 'day_of_week' => 1]),
        };
        $this->expectException(QueryException::class);
        $operation();
    }

    public function test_financial_history_prevents_parent_deletion(): void
    {
        $bill = Bill::factory()->create();
        $this->expectException(QueryException::class);
        $bill->occupancy->delete();
    }

    public function test_spatie_company_roles_are_isolated_and_platform_context_is_separate(): void
    {
        $user = User::factory()->create();
        $first = Company::factory()->create();
        $second = Company::factory()->create();
        $permission = Permission::create(['name' => 'bills.view', 'guard_name' => 'web']);
        $role = Role::create(['company_id' => $first->id, 'name' => 'Billing', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        setPermissionsTeamId($first->id);
        $user->assignRole($role);
        $this->assertTrue($user->hasPermissionTo('bills.view'));
        setPermissionsTeamId($second->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertFalse($user->hasPermissionTo('bills.view'));
        setPermissionsTeamId(0);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertFalse($user->hasPermissionTo('bills.view'));
        $platform = Role::create(['company_id' => 0, 'name' => 'Platform reviewer', 'guard_name' => 'web']);
        $platform->givePermissionTo(Permission::create(['name' => 'platform.companies.review', 'guard_name' => 'web']));
        $user->assignRole($platform);
        $this->assertTrue($user->hasPermissionTo('platform.companies.review'));
        setPermissionsTeamId($first->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertFalse($user->hasPermissionTo('platform.companies.review'));
        setPermissionsTeamId(null);
    }

    public static function integrityCases(): array
    {
        return array_map(fn ($name) => [$name], ['approval_update', 'approval_delete', 'bill_snapshot', 'receipt_snapshot', 'pending_receipt', 'negative_payment', 'invalid_dates', 'invalid_status', 'bill_delete', 'invalid_totals']);
    }

    #[DataProvider('integrityCases')]
    public function test_history_and_value_invariants_are_enforced_even_for_direct_queries(string $invariant): void
    {
        $company = Company::factory()->create();
        $log = CompanyApprovalLog::create(['company_id' => $company->id, 'actor_user_id' => $company->owner_user_id, 'action' => 'submitted', 'from_status' => 'draft', 'to_status' => 'pending_review']);
        $bill = Bill::factory()->create();
        $receipt = Receipt::factory()->create();
        $pending = Payment::factory()->create();
        $occupancy = PropertyOccupancy::factory()->create();
        $operation = match ($invariant) {
            'approval_update' => fn () => DB::table('company_approval_logs')->where('id', $log->id)->update(['remarks' => 'Tampered']),
            'approval_delete' => fn () => DB::table('company_approval_logs')->where('id', $log->id)->delete(),
            'bill_snapshot' => fn () => DB::table('bills')->where('id', $bill->id)->update(['company_snapshot' => '{}']),
            'receipt_snapshot' => fn () => DB::table('receipts')->where('id', $receipt->id)->update(['snapshot' => '{}']),
            'pending_receipt' => fn () => Receipt::factory()->create(['payment_id' => $pending->id]),
            'negative_payment' => fn () => Payment::factory()->create(['amount' => '-1.00']),
            'invalid_dates' => fn () => $occupancy->update(['move_out_date' => $occupancy->move_in_date->subDay()]),
            'invalid_status' => fn () => DB::table('companies')->where('id', $company->id)->update(['status' => 'invalid']),
            'bill_delete' => fn () => DB::table('bills')->where('id', $bill->id)->delete(),
            'invalid_totals' => fn () => Bill::factory()->create(['outstanding_amount' => '1.00']),
        };
        $this->expectException(QueryException::class);
        $operation();
    }

    public function test_a_login_can_only_be_claimed_by_one_resident(): void
    {
        $user = User::factory()->create();
        Resident::factory()->create(['user_id' => $user->id]);
        $this->expectException(QueryException::class);
        Resident::factory()->create(['user_id' => $user->id]);
    }

    public function test_multiple_residents_can_exist_without_login_accounts(): void
    {
        Resident::factory()->count(2)->create();
        $this->assertSame(2, Resident::whereNull('user_id')->count());
    }

    public function test_only_one_open_head_office_is_allowed(): void
    {
        $company = Company::factory()->create();
        $community = Community::factory()->create();
        $attributes = ['company_id' => $company->id, 'label' => 'HQ', 'address_line' => '1 Main Road',
            'lga_id' => $community->lga_id, 'state_id' => $community->lga->state_id,
            'is_head_office' => true, 'active_from' => today()];
        CompanyLocation::create($attributes);
        $this->expectException(QueryException::class);
        CompanyLocation::create($attributes);
    }

    public function test_only_one_pending_invitation_for_an_email_in_a_company(): void
    {
        $company = Company::factory()->create();
        $attributes = ['company_id' => $company->id, 'email' => 'staff@example.com', 'invited_by' => $company->owner_user_id,
            'role_name' => 'Collector', 'expires_at' => now()->addDay()];
        StaffInvitation::create($attributes + ['token_hash' => hash('sha256', 'first')]);
        $this->expectException(QueryException::class);
        StaffInvitation::create($attributes + ['token_hash' => hash('sha256', 'second')]);
    }

    public function test_renewed_coverage_preserves_closed_rows(): void
    {
        $company = Company::factory()->create();
        $community = Community::factory()->create();
        $attributes = ['company_id' => $company->id, 'community_id' => $community->id];
        CompanyServiceArea::create($attributes + ['active_from' => '2025-01-01', 'active_to' => '2026-01-01', 'status' => 'inactive']);
        $current = CompanyServiceArea::create($attributes + ['active_from' => '2026-01-01']);
        $this->assertSame(2, $company->serviceAreas()->count());
        $this->assertTrue(CompanyServiceArea::current()->sole()->is($current));
    }

    public function test_receipt_file_can_be_updated_after_payment_reversal(): void
    {
        $receipt = Receipt::factory()->create();
        $receipt->payment->update(['status' => PaymentStatus::Reversed]);
        $receipt->update(['file_disk' => 'local', 'file_path' => 'receipts/example.pdf']);
        $this->assertSame('receipts/example.pdf', $receipt->fresh()->file_path);
    }
}
