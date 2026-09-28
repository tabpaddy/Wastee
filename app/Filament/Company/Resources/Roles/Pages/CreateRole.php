<?php

namespace App\Filament\Company\Resources\Roles\Pages;

use App\Filament\Company\Resources\Roles\RoleResource;
use App\Filament\Company\Resources\Roles\Schemas\RoleForm;
use App\Services\Staff\CompanyRoleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CompanyRoleService::class)->create(auth()->user(), RoleForm::attributes($data));
    }
}
