<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['bill_id' => Bill::factory(), 'company_id' => fn (array $attributes) => Bill::findOrFail($attributes['bill_id'])->company_id, 'payment_reference' => (string) Str::uuid7(), 'payment_method' => PaymentMethod::BankTransfer, 'amount' => '5000.00', 'currency' => 'NGN'];
    }

    public function successful(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Successful, 'paid_at' => now(), 'verified_at' => now()]);
    }
}
