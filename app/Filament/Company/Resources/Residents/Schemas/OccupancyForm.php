<?php

namespace App\Filament\Company\Resources\Residents\Schemas;

use App\Enums\OccupancyType;
use App\Models\Property;
use App\Models\PropertyOccupancy;
use App\Services\Auth\AuthorizationContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class OccupancyForm
{
    public static function type(): Select
    {
        return Select::make('occupancy_type')->options(collect(OccupancyType::cases())
            ->mapWithKeys(fn ($type) => [$type->value => str($type->value)->replace('_', ' ')->title()->toString()])->all())
            ->default('tenant')->required();
    }

    public static function departure(PropertyOccupancy $occupancy): array
    {
        return [Textarea::make('reason')->required()->minLength(5)->maxLength(2000)
            ->helperText('Effective today. Date-only history cannot close an occupancy that began today.'),
            Select::make('replacement_uuid')->label('Replacement billing contact')
                ->options(fn () => $occupancy->property->occupancies()->current()->whereKeyNot($occupancy->id)->with('resident')->get()
                    ->mapWithKeys(fn ($other) => [$other->uuid => $other->resident->full_name])->all())
                ->helperText('Required when the billing contact leaves other occupants behind.')];
    }

    public static function destination(): Select
    {
        return Select::make('destination_uuid')->label('Destination property')->required()->searchable()
            ->options(fn () => Property::query()->active()->currentlyServedBy(app(AuthorizationContext::class)->companyId() ?? -1)->get()
                ->mapWithKeys(fn ($property) => [$property->uuid => $property->property_code.' / '.$property->street])->all())
            ->helperText('For another provider, record departure only. The receiving provider links the resident separately.');
    }
}
