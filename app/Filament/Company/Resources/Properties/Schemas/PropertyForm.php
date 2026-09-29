<?php

namespace App\Filament\Company\Resources\Properties\Schemas;

use App\Enums\PropertyType;
use App\Models\CompanyServiceArea;
use App\Services\Auth\AuthorizationContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([Select::make('community_uuid')->label('Covered community')->required()->searchable()
            ->options(fn () => CompanyServiceArea::query()->forCompany(app(AuthorizationContext::class)->companyId() ?? -1)
                ->current()->whereHas('community', fn ($query) => $query->active())->with('community.lga')->get()
                ->mapWithKeys(fn ($area) => [$area->community->uuid => $area->community->name.' / '.$area->community->lga->name])->all())
            ->helperText('No choices? Add service coverage using configured reference geography first.'),
            ...self::profileFields(), Toggle::make('confirm_distinct')->label('I confirm this is a different physical property if the address matches an existing record')
                ->helperText('For a known existing property, cancel and use Assign existing property with its exact UUID.')]);
    }

    public static function profileFields(): array
    {
        return [TextInput::make('building_number')->maxLength(100), TextInput::make('street')->required()->maxLength(255),
            TextInput::make('landmark')->maxLength(255),
            Select::make('property_type')->options(collect(PropertyType::cases())->mapWithKeys(fn ($type) => [$type->value => str($type->value)->replace('_', ' ')->title()->toString()])->all())->default('residential')->required(),
            TextInput::make('latitude')->numeric()->minValue(-90)->maxValue(90), TextInput::make('longitude')->numeric()->minValue(-180)->maxValue(180)];
    }
}
