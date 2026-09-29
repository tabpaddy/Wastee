<?php

namespace App\Filament\Company\Resources\Properties\Pages;

use App\Filament\Company\Resources\Properties\PropertyResource;
use App\Services\Operations\PropertyProviderService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make(), Action::make('assignExisting')->label('Assign existing property')
            ->visible(fn () => auth()->user()->can('properties.create'))->requiresConfirmation()
            ->schema([TextInput::make('property_uuid')->label('Exact property UUID')->required()->uuid()
                ->helperText('Obtain the reference from the property holder or former provider. Existing active assignments cannot be taken over.')])
            ->action(fn (array $data) => app(PropertyProviderService::class)->assign(auth()->user(), $data['property_uuid']))];
    }
}
