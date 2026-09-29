<?php

namespace App\Filament\Company\Resources\Properties\Pages;

use App\Filament\Company\Resources\Properties\PropertyResource;
use App\Filament\Company\Resources\Properties\Schemas\PropertyForm;
use App\Filament\Company\Resources\Residents\Schemas\OccupancyForm;
use App\Filament\Company\Resources\Residents\Schemas\ResidentForm;
use App\Services\Auth\AuthorizationContext;
use App\Services\Operations\PropertyProviderService;
use App\Services\Operations\PropertyService;
use App\Services\Operations\ResidentOccupancyService;
use App\Services\Operations\ResidentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewProperty extends ViewRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->editProfile(), $this->registerResident(), $this->linkResident(), $this->billingContact(), $this->releaseProvider()];
    }

    private function editProfile(): Action
    {
        return Action::make('editProperty')->visible(fn () => auth()->user()->can('update', $this->getRecord()))
            ->schema(PropertyForm::profileFields())->fillForm(fn () => $this->getRecord()->attributesToArray())
            ->action(function (array $data): void {
                app(PropertyService::class)->update(auth()->user(), $this->getRecord(), $data);
                $this->refreshPage();
            });
    }

    private function registerResident(): Action
    {
        return Action::make('registerResident')->label('Register occupant')->visible(fn () => auth()->user()->can('occupy', $this->getRecord()))
            ->schema([...ResidentForm::registrationFields(), OccupancyForm::type()])
            ->action(function (array $data): void {
                app(ResidentService::class)->register(auth()->user(), $this->getRecord(), $data);
                $this->refreshPage();
            });
    }

    private function linkResident(): Action
    {
        return Action::make('linkResident')->label('Link existing resident')->visible(fn () => auth()->user()->can('occupy', $this->getRecord()))
            ->schema([TextInput::make('resident_uuid')->label('Exact resident UUID')->required()->uuid()
                ->helperText('No global directory is available. Obtain this reference from the resident or their former provider.'), OccupancyForm::type()])
            ->action(function (array $data): void {
                app(ResidentOccupancyService::class)->addOccupant(auth()->user(), $this->getRecord(), $data['resident_uuid'], $data['occupancy_type']);
                $this->refreshPage();
            });
    }

    private function billingContact(): Action
    {
        return Action::make('billingContact')->label('Change billing contact')->requiresConfirmation()
            ->visible(fn () => auth()->user()->can('manageOccupants', $this->getRecord()))
            ->schema([Select::make('occupancy_uuid')->label('Current occupant')->required()
                ->options(fn () => $this->getRecord()->occupancies()->current()->with('resident')->get()
                    ->mapWithKeys(fn ($occupancy) => [$occupancy->uuid => $occupancy->resident->full_name])->all())])
            ->action(function (array $data): void {
                app(ResidentOccupancyService::class)->changeBillingContact(auth()->user(), $this->getRecord(), $data['occupancy_uuid']);
                $this->refreshPage();
            });
    }

    private function releaseProvider(): Action
    {
        return Action::make('releaseProvider')->color('danger')->requiresConfirmation()
            ->modalDescription('Release this property today. Occupant identities and occupancy history remain. Another covered provider can subsequently assign it.')
            ->visible(fn () => auth()->user()->can('update', $this->getRecord()))
            ->schema([Textarea::make('reason')->required()->minLength(5)->maxLength(2000)])
            ->action(function (array $data): void {
                $assignment = $this->getRecord()->providerAssignments()->forCompany(app(AuthorizationContext::class)->companyId())->current()->firstOrFail();
                app(PropertyProviderService::class)->release(auth()->user(), $assignment, $data['reason']);
                $this->refreshPage();
            });
    }

    private function refreshPage(): void
    {
        $this->redirect(PropertyResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
