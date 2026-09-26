<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return ['community_id' => Community::factory(), 'property_code' => fake()->unique()->uuid(), 'street' => fake()->streetAddress()];
    }
}
