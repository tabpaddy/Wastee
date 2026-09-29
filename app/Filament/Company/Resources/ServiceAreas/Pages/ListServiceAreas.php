<?php

namespace App\Filament\Company\Resources\ServiceAreas\Pages;

use App\Filament\Company\Resources\ServiceAreas\Schemas\ServiceAreaForm;
use App\Filament\Company\Resources\ServiceAreas\ServiceAreaResource;
use App\Services\Operations\CompanyServiceAreaService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListServiceAreas extends ListRecords
{
    protected static string $resource = ServiceAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('addCoverage')->visible(fn () => auth()->user()->can('service-areas.manage'))
            ->schema(ServiceAreaForm::fields())->action(fn (array $data) => app(CompanyServiceAreaService::class)->add(auth()->user(), $data['community_uuid']))];
    }
}
