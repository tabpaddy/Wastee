<?php

namespace App\Filament\Company\Resources\Roles\Pages;

use App\Filament\Company\Resources\Roles\RoleResource;
use App\Filament\Company\Resources\Roles\Schemas\RoleForm;
use App\Services\Staff\CompanyRoleService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permission_groups'] = RoleForm::groups($this->getRecord()->permissions->pluck('name')->all());

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(CompanyRoleService::class)->update(auth()->user(), $record, RoleForm::attributes($data));
    }
}
