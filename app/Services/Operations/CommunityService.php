<?php

namespace App\Services\Operations;

use App\Models\Community;
use App\Models\Lga;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CommunityService
{
    public function create(User $actor, array $attributes): Community
    {
        return DB::transaction(function () use ($actor, $attributes): Community {
            Gate::forUser($actor)->authorize('create', Community::class);
            $validated = Validator::make($attributes, ['lga_id' => ['required', 'integer', 'exists:lgas,id'],
                'name' => ['required', 'string', 'max:255'], 'ward' => ['nullable', 'string', 'max:255'],
                'postal_code' => ['nullable', 'string', 'max:30']])->validate();
            $lga = Lga::query()->whereKey($validated['lga_id'])->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', Community::class);
            $validated['name'] = trim($validated['name']);
            Validator::make($validated, ['name' => [Rule::unique('communities')->where('lga_id', $lga->id)]])->validate();

            return Community::create([...$validated, 'status' => 'active']);
        });
    }

    public function update(User $actor, Community $community, array $attributes): void
    {
        DB::transaction(function () use ($actor, $community, $attributes): void {
            Gate::forUser($actor)->authorize('update', $community);
            $community = Community::query()->whereKey($community->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $community);
            $validated = Validator::make($attributes, ['name' => ['required', 'string', 'max:255',
                Rule::unique('communities')->where('lga_id', $community->lga_id)->ignore($community->id)],
                'ward' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:30']])->validate();
            $community->update($validated);
        });
    }
}
