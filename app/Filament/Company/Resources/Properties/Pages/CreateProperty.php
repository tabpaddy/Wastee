<?php

namespace App\Filament\Company\Resources\Properties\Pages;

use App\Filament\Company\Resources\Properties\PropertyResource;
use App\Services\Operations\PropertyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PropertyService::class)->create(auth()->user(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return PropertyResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
