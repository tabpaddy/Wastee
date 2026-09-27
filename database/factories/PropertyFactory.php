<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return ['community_id' => Community::factory(), 'property_code' => (string) Str::uuid7(), 'street' => fake()->streetAddress()];
    }
}
