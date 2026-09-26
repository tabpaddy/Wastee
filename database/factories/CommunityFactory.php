<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Lga;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Community> */
class CommunityFactory extends Factory
{
    public function definition(): array
    {
        return ['lga_id' => Lga::factory(), 'name' => fake()->unique()->city()];
    }
}
