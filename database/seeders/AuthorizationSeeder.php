<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Auth\RoleProvisioner;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    public function run(RoleProvisioner $roles): void
    {
        $roles->seedPlatform();
        Company::query()->eachById(fn (Company $company) => $roles->seedCompany($company));
    }
}
