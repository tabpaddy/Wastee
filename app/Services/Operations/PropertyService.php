<?php

namespace App\Services\Operations;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Community;
use App\Models\Company;
use App\Models\Property;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyService
{
    public function __construct(private AuthorizationContext $context, private PropertyProviderService $providers) {}

    public function create(User $actor, array $attributes): Property
    {
        return DB::transaction(function () use ($actor, $attributes): Property {
            Gate::forUser($actor)->authorize('create', Property::class);
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $validated = Validator::make($attributes, ['community_uuid' => ['required', 'uuid'], ...self::rules(),
                'confirm_distinct' => ['sometimes', 'boolean']])->validate();
            $community = Community::query()->active()->where('uuid', $validated['community_uuid'])->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', Property::class);
            $property = new Property(['community_id' => $community->id]);
            $this->providers->coverage($property);
            $address = $this->normalizeAddress($validated);
            $duplicate = $community->properties()->whereRaw('LOWER(TRIM(street)) = ?', [strtolower($address['street'])])
                ->whereRaw("LOWER(TRIM(COALESCE(building_number, ''))) = ?", [strtolower($address['building_number'] ?? '')])->exists();
            if ($duplicate && ! ($validated['confirm_distinct'] ?? false)) {
                throw ValidationException::withMessages(['confirm_distinct' => 'An address match exists. Reuse its exact property UUID, or explicitly confirm this is a different physical property.']);
            }
            $property->fill($address);
            $property->property_code = 'WST-'.Str::uuid7();
            $property->status = PropertyStatus::Active;
            $property->save();
            $this->providers->assign($actor, $property->uuid);

            return $property;
        });
    }

    public function update(User $actor, Property $property, array $attributes): Property
    {
        return DB::transaction(function () use ($actor, $property, $attributes): Property {
            Gate::forUser($actor)->authorize('update', $property);
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $property = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $property);
            $property->update($this->normalizeAddress(Validator::make($attributes, self::rules())->validate()));

            return $property;
        });
    }

    public static function rules(): array
    {
        return ['building_number' => ['nullable', 'string', 'max:100'], 'street' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'], 'property_type' => ['required', Rule::enum(PropertyType::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180']];
    }

    private function normalizeAddress(array $attributes): array
    {
        $address = array_intersect_key($attributes, self::rules());
        foreach (['street', 'building_number', 'landmark'] as $field) {
            if (isset($address[$field])) {
                $address[$field] = preg_replace('/\\s+/', ' ', trim($address[$field]));
            }
        }

        return $address;
    }
}
