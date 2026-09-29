<?php

namespace App\Filament\Company\Resources\Residents\Pages;

use App\Filament\Company\Resources\Residents\ResidentResource;
use App\Filament\Company\Resources\Residents\Schemas\ResidentForm;
use App\Services\Operations\ResidentService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewResident extends ViewRecord
{
    protected static string $resource = ResidentResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('editResident')->visible(fn () => auth()->user()->can('update', $this->getRecord()))
            ->schema(ResidentForm::profileFields())->fillForm(fn () => $this->getRecord()->attributesToArray())
            ->action(function (array $data): void {
                app(ResidentService::class)->update(auth()->user(), $this->getRecord(), $data);
                $this->redirect(ResidentResource::getUrl('view', ['record' => $this->getRecord()]));
            })];
    }
}
