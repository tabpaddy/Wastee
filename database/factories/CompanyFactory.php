<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return ['owner_user_id' => User::factory(), 'name' => fake()->company(), 'slug' => fake()->unique()->slug().'-'.fake()->uuid(), 'email' => fake()->companyEmail(), 'phone' => fake()->phoneNumber()];
    }
}
