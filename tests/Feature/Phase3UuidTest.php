<?php

namespace Tests\Feature;

use App\Models\CompanyServiceArea;
use App\Models\Complaint;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Resident;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\Phase3TestCase;

class Phase3UuidTest extends Phase3TestCase
{
    public function test_concern_issues_unique_version_seven_ids_for_every_uuid_model(): void
    {
        $seen = [];
        foreach (glob(app_path('Models/*.php')) as $path) {
            $class = 'App\\Models\\'.basename($path, '.php');
            if (! in_array(HasPublicUuid::class, class_uses_recursive($class), true)) {
                continue;
            }
            $model = new $class;
            $this->assertSame(['uuid'], $model->uniqueIds());
            $this->assertSame('int', $model->getKeyType());
            $this->assertTrue($model->getIncrementing());
            foreach (range(1, 3) as $i) {
                $uuid = $model->newUniqueId();
                $this->assertTrue(Str::isUuid($uuid));
                $this->assertSame(7, Uuid::fromString($uuid)->getVersion());
                $this->assertNotContains($uuid, $seen);
                $seen[] = $uuid;
            }
        }
        $this->assertFalse(Schema::hasColumn((new CompanyServiceArea)->getTable(), 'uuid'));
    }

    public function test_automatic_persisted_uuids_keep_bigint_relationships(): void
    {
        [$owner, $company] = $this->ready();
        $receipt = Receipt::factory()->create();
        $resident = Resident::factory()->create();
        $property = Property::factory()->create();
        $complaint = Complaint::create(['company_id' => $company->id, 'resident_id' => $resident->id,
            'category' => 'collection', 'subject' => 'Missed pickup', 'description' => 'A test complaint.']);
        $records = [$owner, $company, $company->documents()->first(), $company->locations()->first(),
            $property, $resident, $receipt, $receipt->payment, $receipt->payment->bill, $complaint];
        $seen = [];
        foreach ($records as $record) {
            $this->assertSame(7, Uuid::fromString($record->uuid)->getVersion());
            $this->assertIsInt($record->id);
            $this->assertNotContains($record->uuid, $seen);
            $seen[] = $record->uuid;
        }
        $this->assertSame($owner->id, $company->owner_user_id);
        $this->assertSame($company->id, $company->documents()->first()->company_id);
        $this->assertSame($receipt->payment->id, $receipt->payment_id);
        $this->assertSame(7, Uuid::fromString($receipt->payment->payment_reference)->getVersion());
        $this->assertSame(7, Uuid::fromString($property->property_code)->getVersion());
    }
}
