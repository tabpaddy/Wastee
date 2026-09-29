<?php

namespace App\Filament\Company\Resources\Residents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ResidentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('full_name')->label('Name'), TextEntry::make('uuid')->label('Resident reference'),
            TextEntry::make('phone'), TextEntry::make('email'), TextEntry::make('status')->badge(),
            TextEntry::make('currentOccupancy.property.property_code')->label('Current property served by this company')->placeholder('Historical relationship only'),
            TextEntry::make('currentOccupancy.property.street')->label('Current street'),
            TextEntry::make('currentOccupancy.property.currentProviderAssignment.company.name')->label('Current provider'),
            TextEntry::make('currentOccupancy.occupancy_type')->label('Occupancy type')->badge(),
            TextEntry::make('currentOccupancy.is_billing_contact')->label('Current billing contact')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')]);
    }
}
