<?php

namespace App\Services\Operations;

use App\Enums\ResidentStatus;
use App\Models\Company;
use App\Models\Property;
use App\Models\Resident;
use App\Models\User;
use App\Services\Auth\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResidentService
{
    public function __construct(private AuthorizationContext $context, private ResidentOccupancyService $occupancies) {}

    public function register(User $actor, Property $property, array $attributes): Resident
    {
        return DB::transaction(function () use ($actor, $property, $attributes): Resident {
            Gate::forUser($actor)->authorize('occupy', $property);
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $property = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('occupy', $property);
            $profile = $this->profile($attributes);
            $duplicate = Resident::query()->where(function ($query) use ($profile): void {
                $query->where('phone', $profile['phone']);
                if ($profile['email'] ?? null) {
                    $query->orWhereRaw('LOWER(email) = ?', [$profile['email']]);
                }
            })->exists();
            $confirmed = Validator::make($attributes, ['confirm_distinct' => ['sometimes', 'boolean']])->validate();
            if ($duplicate && ! ($confirmed['confirm_distinct'] ?? false)) {
                throw ValidationException::withMessages(['confirm_distinct' => 'Contact details match an existing record. Use the resident UUID or confirm this is a different person. No identity is merged automatically.']);
            }
            $resident = Resident::create([...$profile, 'status' => ResidentStatus::Active]);
            $this->occupancies->addOccupant($actor, $property, $resident->uuid, $attributes['occupancy_type'] ?? 'tenant');

            return $resident;
        });
    }

    public function update(User $actor, Resident $resident, array $attributes): Resident
    {
        return DB::transaction(function () use ($actor, $resident, $attributes): Resident {
            Gate::forUser($actor)->authorize('update', $resident);
            Company::query()->whereKey($this->context->companyId())->lockForUpdate()->firstOrFail();
            $resident = Resident::query()->whereKey($resident->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $resident);
            $resident->update($this->profile($attributes));

            return $resident;
        });
    }

    public static function rules(): array
    {
        return ['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\\+?[0-9 ()-]{7,40}$/'],
            'email' => ['nullable', 'email', 'max:255'], 'gender' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'], 'occupation' => ['nullable', 'string', 'max:255']];
    }

    private function profile(array $attributes): array
    {
        $profile = Validator::make($attributes, self::rules())->validate();
        $profile['phone'] = preg_replace('/[ ()-]/', '', $profile['phone']);
        Validator::make($profile, ['phone' => ['required', 'regex:/^\\+?[0-9]{7,40}$/']])->validate();
        $profile['email'] = empty($profile['email']) ? null : strtolower(trim($profile['email']));
        $profile['first_name'] = trim($profile['first_name']);
        $profile['last_name'] = trim($profile['last_name']);

        return $profile;
    }
}
