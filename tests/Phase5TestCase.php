<?php

namespace Tests;

use App\Models\Community;
use App\Models\Company;
use App\Models\Property;
use App\Models\Resident;
use App\Models\User;
use App\Services\Operations\PropertyService;
use App\Services\Operations\ResidentService;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

abstract class Phase5TestCase extends Phase2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function covered(string $role = 'Owner', ?Community $community = null): array
    {
        [$actor, $company] = $this->companyUser($role);
        $community ??= Community::factory()->create();
        $area = $company->serviceAreas()->create(['community_id' => $community->id, 'active_from' => today()->subMonth()]);

        return [$actor, $company, $community, $area];
    }

    protected function property(User $actor, Company $company, Community $community, array $attributes = []): Property
    {
        return $this->context()->runForCompany($actor, $company, fn () => app(PropertyService::class)->create($actor,
            [...['community_uuid' => $community->uuid, 'building_number' => (string) random_int(1, 999999),
                'street' => 'Fixture Street', 'property_type' => 'residential'], ...$attributes]));
    }

    protected function resident(User $actor, Company $company, Property $property, array $attributes = []): Resident
    {
        return $this->context()->runForCompany($actor, $company, fn () => app(ResidentService::class)->register($actor, $property,
            [...['first_name' => 'Ada', 'last_name' => 'Fixture', 'phone' => '+234'.random_int(1000000000, 1999999999)], ...$attributes]));
    }

    protected function snapshot(TestResponse $response, string $component): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if (str_contains(json_decode($snapshot, true)['memo']['name'], $component)) {
                return $snapshot;
            }
        }
        $this->fail('Missing snapshot: '.$component);
    }

    protected function livewireCall(string $snapshot, string $method, array $params = [], array $updates = []): TestResponse
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [
            ['snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => $params]]],
        ]], ['X-Livewire' => 'true']);
    }
}
