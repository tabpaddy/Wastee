<?php

namespace App\Filament\Platform\Resources\Communities\Schemas;

use App\Models\Lga;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CommunityForm
{
    public static function fields(bool $creating = true): array
    {
        $fields = [TextInput::make('name')->required()->maxLength(255), TextInput::make('ward')->maxLength(255),
            TextInput::make('postal_code')->maxLength(30)];
        if ($creating) {
            array_unshift($fields, Select::make('lga_id')->label('LGA')->required()->searchable()
                ->options(fn () => Lga::query()->with('state')->orderBy('name')->get()->mapWithKeys(fn ($lga) => [$lga->id => $lga->name.' / '.$lga->state->name])->all())
                ->helperText('No choices? Import verified State/LGA reference data. This form cannot create States or LGAs.'));
        }

        return $fields;
    }
}
