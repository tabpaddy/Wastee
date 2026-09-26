<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyOccupancy;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyOccupancy> */
class PropertyOccupancyFactory extends Factory
{
    public function definition(): array
    {
        return ['resident_id' => Resident::factory(), 'property_id' => Property::factory(), 'move_in_date' => today()->subMonth()];
    }
}
