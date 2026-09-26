<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ServicePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServicePlan> */
class ServicePlanFactory extends Factory
{
    public function definition(): array
    {
        return ['company_id' => Company::factory(), 'name' => 'Monthly residential collection', 'amount' => '5000.00', 'effective_from' => today()->startOfYear()];
    }
}
