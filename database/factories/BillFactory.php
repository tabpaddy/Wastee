<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Company;
use App\Models\PropertyOccupancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bill> */
class BillFactory extends Factory
{
    public function definition(): array
    {
        return ['company_id' => Company::factory(), 'property_occupancy_id' => PropertyOccupancy::factory(), 'invoice_number' => 'INV-'.fake()->unique()->uuid(), 'billing_period_start' => today()->startOfMonth(), 'billing_period_end' => today()->endOfMonth(), 'subtotal' => '5000.00', 'total_amount' => '5000.00', 'outstanding_amount' => '5000.00', 'status' => BillStatus::Issued, 'issued_at' => now(), 'due_at' => now()->addDays(14), 'created_by' => fn (array $attributes) => Company::findOrFail($attributes['company_id'])->owner_user_id, 'resident_snapshot' => ['name' => 'Original Resident', 'phone' => '08000000000'], 'property_snapshot' => ['address' => '10 Original Street'], 'company_snapshot' => ['name' => 'Original Waste Company', 'address' => '1 Original Office'], 'plan_snapshot' => ['name' => 'Monthly collection', 'rate' => '5000.00', 'billing_cycle' => 'monthly']];
    }
}
