<?php

namespace App\Filament\Company\Resources\Residents\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ResidentForm
{
    public static function profileFields(): array
    {
        return [TextInput::make('first_name')->required()->maxLength(100), TextInput::make('last_name')->required()->maxLength(100),
            TextInput::make('phone')->tel()->required()->maxLength(40), TextInput::make('email')->email()->maxLength(255),
            TextInput::make('gender')->maxLength(50), DatePicker::make('date_of_birth')->maxDate(today()),
            TextInput::make('occupation')->maxLength(255)];
    }

    public static function registrationFields(): array
    {
        return [...self::profileFields(), Toggle::make('confirm_distinct')->label('I confirm this is a different person if contact details match')
            ->helperText('To reuse an existing person, use Link existing resident with their exact UUID. A login account is not created.')];
    }
}
