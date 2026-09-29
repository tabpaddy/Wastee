<?php

namespace App\Filament\Company\Resources\Properties\Schemas;

use App\Services\Auth\AuthorizationContext;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PropertyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('property_code'), TextEntry::make('uuid')->label('Property reference'),
            TextEntry::make('property_type')->badge(), TextEntry::make('status')->badge(), TextEntry::make('building_number'),
            TextEntry::make('street'), TextEntry::make('landmark'), TextEntry::make('community.name'),
            TextEntry::make('community.lga.name')->label('LGA'), TextEntry::make('community.lga.state.name')->label('State'),
            TextEntry::make('currentProviderAssignment.company.name')->label('Current service by this company')->placeholder('Historical relationship only'),
            TextEntry::make('billing_contact')->visible(fn ($record) => auth()->user()->can('residents.view') && $record->currentProviderAssignment !== null)
                ->state(fn ($record) => $record->occupancies()->current()->billingContact()
                    ->visibleToCompany(app(AuthorizationContext::class)->companyId())->with('resident')->first()?->resident->full_name)
                ->placeholder('No billing contact selected')]);
    }
}
