<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<State> */
class StateFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->state(), 'country_code' => 'NG'];
    }
}
