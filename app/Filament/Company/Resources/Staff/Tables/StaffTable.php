<?php

namespace App\Filament\Company\Resources\Staff\Tables;

use App\Services\Staff\CompanyRoleService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('Name')->searchable(),
            TextColumn::make('user.email')->label('Email')->searchable(),
            TextColumn::make('user.phone')->label('Phone'),
            TextColumn::make('user.roles.name')->label('Roles')->badge(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => ucfirst($state->value))->badge(),
            TextColumn::make('joined_at')->dateTime()->sortable(),
            TextColumn::make('user.last_login_at')->dateTime(),
        ])->filters([
            SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended']),
            SelectFilter::make('role')->options(fn () => app(CompanyRoleService::class)->staffFilterOptions(auth()->user()))
                ->query(fn ($query, array $data) => $query->when($data['value'] ?? null,
                    fn ($query, $roleUuid) => $query->whereHas('user.roles', fn ($roles) => $roles->where('roles.uuid', $roleUuid)))),
        ])->recordActions([ViewAction::make()])->defaultSort('joined_at', 'desc');
    }
}
