<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        return ['payment_id' => Payment::factory()->successful(), 'company_id' => fn (array $attributes) => Payment::findOrFail($attributes['payment_id'])->company_id, 'receipt_number' => 'RCT-'.fake()->unique()->uuid(), 'generated_at' => now(), 'snapshot' => ['resident_name' => 'Original Resident', 'company_name' => 'Original Waste Company', 'company_address' => '1 Original Office', 'property_address' => '10 Original Street', 'amount' => '5000.00', 'currency' => 'NGN', 'payment_method' => 'bank_transfer']];
    }
}
